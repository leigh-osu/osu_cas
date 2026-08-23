<?php

namespace Drupal\ckeditor_scayt\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\editor\Entity\Editor;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\ckeditor\CKEditorPluginContextualInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the "scayt" plugin.
 *
 * NOTE: The plugin ID ('id' key) corresponds to the CKEditor plugin name.
 * It is the first argument of the CKEDITOR.plugins.add() function in the
 * plugin.js file.
 *
 * @CKEditorPlugin(
 *   id = "scayt",
 *   label = @Translation("SCAYT spellchecker")
 * )
 */
class ScaytCKEditorButton extends CKEditorPluginBase implements ContainerFactoryPluginInterface, CKEditorPluginConfigurableInterface, CKEditorPluginContextualInterface {

  /**
   * Stores the configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Stores the configuration for the current module.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configModule;

  /**
   * The library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Constructs a \Drupal\ckeditor\Plugin\CKEditorPlugin\Internal object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param Drupal\Core\Asset\LibraryDiscoveryInterface $library_discovery
   *   The library discovery interface.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The module extension list.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, LibraryDiscoveryInterface $library_discovery, ModuleExtensionList $extension_list_module) {
    $this->configFactory = $config_factory;
    $this->configModule = $this->configFactory->getEditable('ckeditor_scayt.config');
    $this->libraryDiscovery = $library_discovery;
    $this->moduleExtensionList = $extension_list_module;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('library.discovery'),
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   *
   * NOTE: The keys of the returned array corresponds to the CKEditor button
   * names. They are the first argument of the editor.ui.addButton() or
   * editor.ui.addRichCombo() functions in the plugin.js file.
   */
  public function getButtons() {
    // Make sure that the path to the image matches the file structure of
    // the CKEditor plugin you are implementing.
    $version = $this->getCkEditorVersion();
    $path = $this->moduleExtensionList->getPath('ckeditor_scayt') . '/dist/scayt/' . $version;
    return [
      'Scayt' => [
        'label' => $this->t('SCAYT spellchecker'),
        'image' => $path . '/icons/scayt.png',
      ],
    ];
  }

  /**
   * Returns current version of CKEditor.
   *
   * @return string
   *   CKEditor version (either 4.17, 4.18 or 4.19 (for any other version)).
   */
  public function getCkEditorVersion() {
    return '4.23.0-lts';
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return $this->getLibraryPath() . '/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraryPath() {
    $version = $this->getCkEditorVersion();
    return $this->moduleExtensionList->getPath('ckeditor_scayt') . '/dist/scayt/' . $version;
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    // Defaults.
    $config = $this->getConfig($editor);

    $form['scayt_autoStartup'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto startup'),
      '#description' => $this->t('Enables Grammar As You Type (GRAYT) on SCAYT startup. When enabled, this option turns on GRAYT automatically after SCAYT started.'),
      '#default_value' => $config['scayt_autoStartup'],
    ];

    // Populate language options.
    $languages = $this->configModule->get('languages');
    $options = [];
    foreach ($languages as $language) {
      $parts = explode('|', $language);
      $options[$parts[0]] = $parts[1];
    }

    $form['scayt_sLang'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $options,
      '#description' => $this->t('Sets the default spell checking language for SCAYT.'),
      '#default_value' => $config['scayt_sLang'],
    ];

    $help_text = <<<'EOT'
<p>Disables storing of SCAYT options between sessions. Option storing will be turned off after a page refresh.
The following settings can be used:</p>
<ul>
<li><code>options</code> â€“ Disables storing of all SCAYT Ignore options.</li>
<li><code>ignore-all-caps-words</code> â€“ Disables storing of the "Ignore All-Caps Words" option.</li>
<li><code>ignore-domain-names</code> â€“ Disables storing of the "Ignore Domain Names" option.</li>
<li><code>ignore-words-with-mixed-cases</code> â€“ Disables storing of the "Ignore Words with Mixed Case" option.</li>
<li><code>ignore-words-with-numbers</code> â€“ Disables storing of the "Ignore Words with Numbers" option.</li>
<li><code>lang</code> â€“ Disables storing of the SCAYT spell check language.</li>
<li><code>all</code> â€“ Disables storing of all SCAYT options.</li>
</ul>
<p>You may enter multiple values with comma separated.</p>
EOT;

    $form['scayt_disableOptionsStorage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Disable options storage'),
      '#description' => $help_text,
      '#default_value' => implode(',', $config['scayt_disableOptionsStorage']),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    // Defaults that provide expected basic behavior.
    // Doc: https://ckeditor.com/docs/ckeditor4/latest/api/CKEDITOR_config.html#cfg-scayt_disableOptionsStorage.
    $config = [
      'scayt_autoStartup' => TRUE,
      'scayt_sLang' => 'en_GB',
      'scayt_disableOptionsStorage' => ['all'],
    ];
    $settings = $editor->getSettings();

    if (isset($settings['plugins']['scayt'])) {
      $config = $settings['plugins']['scayt'] + $config;
      if (isset($config['scayt_disableOptionsStorage']) && is_string($config['scayt_disableOptionsStorage'])) {
        if (empty(trim($config['scayt_disableOptionsStorage']))) {
          unset($config['scayt_disableOptionsStorage']);
        }
        else {
          $config['scayt_disableOptionsStorage'] = explode(',', $config['scayt_disableOptionsStorage']);
          foreach ($config['scayt_disableOptionsStorage'] as $index => $item) {
            $config['scayt_disableOptionsStorage'][$index] = trim($item);
          }
        }
      }
      // Drupal form submission stores integer (0 or 1).
      // It does not work without casting to boolean.
      $config['scayt_autoStartup'] = (bool) $config['scayt_autoStartup'];
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(Editor $editor) {
    $settings = $editor->getSettings();
    foreach ($settings['toolbar']['rows'] as $row) {
      foreach ($row as $group) {
        foreach ($group['items'] as $button) {
          if ($button === 'Scayt') {
            return TRUE;
          }
        }
      }
    }

    return FALSE;
  }

}
