<?php

namespace Drupal\osu_migrations;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Template\Attribute;
use Drupal\migrate\MigrateLookupInterface;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Replace the old WYSIWYG Embed code with the new Media Embed code.
 */
class OsuMediaEmbed {

  /**
   * The Migrate lookup interface.
   *
   * @var \Drupal\migrate\MigrateLookupInterface
   */
  protected $lookup;

  /**
   * The Entity type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an OsuMediaEmbed object.
   *
   * @param \Drupal\migrate\MigrateLookupInterface $lookup
   *   The migrate.lookup service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(MigrateLookupInterface $lookup, EntityTypeManagerInterface $entity_type_manager) {
    $this->lookup = $lookup;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Parse the string and replace the old fid embed with the new media embed.
   *
   * @param string $value
   *   The Body value to check for and replace the Drupal 7 Embed Code.
   *
   * @return string
   *   The full processed body value with either the new embed code or
   *   unchanged.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\migrate\MigrateException
   */
  public function transformEmbedCode(string $value) {
    // Find our old encoded data and save it a capture group called tag_info.
    $pattern = '/\[\[\s*(?<tag_info>\{.+\})\s*\]\]/sU';
    // If we can use associative array use it.
    if (defined(JsonDecode::class . '::ASSOCIATIVE')) {
      $decoder = new JsonDecode([JsonDecode::ASSOCIATIVE => TRUE]);
    }
    else {
      $decoder = new JsonDecode();
    }
    $text = preg_replace_callback($pattern, function ($matches) use ($decoder) {
      // Find 2 or more consecutive spaces and replace it with one.
      $matches['tag_info'] = preg_replace('/\s+/', ' ', $matches['tag_info']);
      try {
        $tag_info = $decoder->decode($matches['tag_info'], JsonEncoder::FORMAT);
        if (!is_array($tag_info) || !array_key_exists('fid', $tag_info)) {
          return $matches[0];
        }
        // Get the ID and view mode.
        $embed_metadata = [
          'id' => $tag_info['fid'],
          'view_mode' => $tag_info['view_mode'] ?? 'default',
        ];
        // Check to see if we have attributes and if not create an empty array.
        $source_attributes = !empty($tag_info['attributes']) ? $tag_info['attributes'] : [];
        // Add alt and title overrides.
        foreach (['alt', 'title'] as $attribute_name) {
          if (!empty($source_attributes[$attribute_name])) {
            $embed_metadata[$attribute_name] = $source_attributes[$attribute_name];
          }
        }

        // Carry an inline size onto the embed: D7 editors resized media
        // tokens with style="width: NNNpx" (or width attributes), and the
        // core MediaEmbed filter transfers a style attribute onto the
        // rendered wrapper, where img-fluid scales the image to it. Width
        // only, so the aspect ratio is preserved.
        $width = NULL;
        if (!empty($source_attributes['style']) && preg_match('/(?:^|[^-])width:\s*(\d+)px/', $source_attributes['style'], $style_match)) {
          $width = (int) $style_match[1];
        }
        elseif (!empty($source_attributes['width']) && is_numeric($source_attributes['width'])) {
          $width = (int) $source_attributes['width'];
        }
        if ($width) {
          $embed_metadata['style'] = "width: {$width}px;";
        }

        // Get the alignment classes.
        if (!empty($source_attributes['class']) && is_string($source_attributes['class'])) {
          $classes_arr = array_unique(explode(' ', preg_replace('/\s{2,}/', ' ', trim($source_attributes['class']))));
          $old_alignment = [
            'media-wysiwyg-align-center' => 'center',
            'media-wysiwyg-align-left' => 'left',
            'media-wysiwyg-align-right' => 'right',
          ];
          foreach ($old_alignment as $old => $new) {
            if (in_array($old, $classes_arr, TRUE)) {
              $embed_metadata['data-align'] = $new;
            }
          }
        }
        $embed_code = $this->getEmbedCode($embed_metadata);
        if ($embed_code === NULL) {
          return $matches[0];
        }

        // CAS: a D7 media token could carry an external_url, which rendered
        // the media as a link -- typically an image thumbnail linking to the
        // PDF it illustrates. 2,702 tokens across 1,075 text rows use it.
        // Without this the image survives and the link it wrapped is dropped
        // silently, so the page still looks right while the download is
        // gone. The href keeps its D7 value on purpose: cas_legacy_file_paths
        // runs after this step and rewrites quoted hrefs to the D10 files
        // path. Other token fields are booleans when unset, hence the string
        // check.
        $external_url = $tag_info['fields']['external_url'] ?? NULL;
        if (is_string($external_url) && trim($external_url) !== '') {
          return '<a href="' . htmlspecialchars(trim($external_url), ENT_QUOTES, 'UTF-8') . '">'
            . $embed_code . '</a>';
        }
        return $embed_code;
      }
      catch (NotEncodableValueException $e) {
        return $matches[0];
      }
      catch (\LogicException $e) {
        return $matches[0];
      }
    }, $value);
    return $this->liftLinkedMedia($text);
  }

  /**
   * Moves linked media embeds out of the paragraphs that enclose them.
   *
   * D7 put an inline <img> inside a <p>; the D10 equivalent, <drupal-media>,
   * renders as a block-level <article>, which is invalid inside a <p>. The
   * full_html format runs filter_htmlcorrector, so the media gets lifted out
   * at render time and the <a> we wrapped it in is left behind empty -- the
   * image shows, nothing is clickable.
   *
   * Authoring the same thing in D10 could not produce that markup: CKEditor
   * treats a media embed as a block widget and never nests one in a
   * paragraph. So the fix belongs here, emitting what native editing would:
   * the linked media as a block-level sibling. A paragraph that also holds
   * text is split around it, which is what the corrector would have done
   * anyway, only without breaking the link.
   */
  private function liftLinkedMedia(string $html) {
    if (!str_contains($html, '<drupal-media')) {
      return $html;
    }
    $document = Html::load($html);
    $xpath = new \DOMXPath($document);
    $paragraphs = $xpath->query('//p[.//a[.//drupal-media]]');
    if (!$paragraphs->length) {
      return $html;
    }
    foreach (iterator_to_array($paragraphs) as $paragraph) {
      $parent = $paragraph->parentNode;
      if (!$parent) {
        continue;
      }
      $replacements = [];
      $run = $document->createElement('p');
      foreach (iterator_to_array($paragraph->childNodes) as $child) {
        $is_linked_media = $child instanceof \DOMElement
          && strtolower($child->nodeName) === 'a'
          && $xpath->query('.//drupal-media', $child)->length > 0;
        if ($is_linked_media) {
          if (self::hasContent($run)) {
            $replacements[] = $run;
          }
          $replacements[] = $child;
          $run = $document->createElement('p');
          continue;
        }
        // Moves the node out of the original paragraph.
        $run->appendChild($child);
      }
      if (self::hasContent($run)) {
        $replacements[] = $run;
      }
      foreach ($replacements as $node) {
        $parent->insertBefore($node, $paragraph);
      }
      $parent->removeChild($paragraph);
    }
    return Html::serialize($document);
  }

  /**
   * Whether a node holds anything worth keeping as its own paragraph.
   */
  private static function hasContent(\DOMNode $node) {
    if (trim($node->textContent) !== '') {
      return TRUE;
    }
    foreach ($node->childNodes as $child) {
      if ($child instanceof \DOMElement) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get the new drupal media embed code.
   *
   * @param array $embedMetadata
   *   An array of media data.
   *
   * @return string|null
   *   Either return the new media embed code or null.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\migrate\MigrateException
   */
  private function getEmbedCode(array $embedMetadata) {
    if (empty($embedMetadata['id']) || empty($embedMetadata['view_mode'])) {
      return NULL;
    }
    // Get the New media ID, could be in any one of these migration.
    $newMid = $this->lookup->lookup([
      'upgrade_d7_media_audio',
      'upgrade_d7_media_documents',
      'upgrade_d7_media_images',
      'cas_media_private_images',
      'cas_media_private_documents',
      'upgrade_d7_media_kaltura',
      'upgrade_d7_media_local_video',
      'upgrade_d7_media_remote_video',
    ], [$embedMetadata['id']]);
    if (empty($newMid)) {
      return NULL;
    }
    // Lookup returns a nested array, we only need the id.
    $newMid = reset($newMid)['mid'];
    /** @var \Drupal\media\Entity\Media $mediaEntity */
    $mediaEntity = $this->entityTypeManager->getStorage('media')
      ->load($newMid);
    // Get the UUID of the media object.
    $mediaEntityUuid = $mediaEntity->uuid();

    $attributes = [];
    $attributes['data-entity-type'] = 'media';
    $attributes['data-entity-uuid'] = $mediaEntityUuid;
    $attributes['data-view-mode'] = 'default';
    // Alt, title, caption and align should be handled conditionally.
    $conditionalAttributes = ['alt', 'title', 'data-caption', 'data-align', 'style'];
    foreach ($conditionalAttributes as $conditionalAttribute) {
      if (!empty($embedMetadata[$conditionalAttribute])) {
        $attributes[$conditionalAttribute] = $embedMetadata[$conditionalAttribute];
      }
    }
    /** @var \Drupal\Core\Template\Attribute $attribute */
    $attribute = new Attribute($attributes);
    return "<drupal-media {$attribute->__toString()}></drupal-media>";
  }

}
