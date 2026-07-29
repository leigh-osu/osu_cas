<?php

namespace Drupal\linkpurpose\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class to define all settings of the module.
 */
class LinkpurposeSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'linkpurpose_form_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'linkpurpose.settings',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('linkpurpose.settings');

    $form['base'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Basic configuration'),
    ];

    $form['base']['text'] = [
      '#markup' => '<p>' . $this->t('Most sites only need to define their domain:') . '</p>',
    ];

    $form['base']['domain'] = [
      '#title' => $this->t("Additional domains to consider internal in absolute URLs"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => empty($config->get('domain')) ? $GLOBALS['base_url'] : $config->get('domain'),
      '#description' => $this->t(
        'The current active domain is always considered internal.<br>Provide a comma separated list of domains, optionally including protocols, trailing paths, subdomains or * as a leading subdomain wildcard:<br>
            e.g.: <code><em>www.only-www.net, *.all-subdomains.net, a-shared-site.net/just-my-blog</em></code>'
      ),
      '#default_value' => $config->get('domain'),
    ];

    /* Base fieldset. */
    $form['base']['containers'] = [
      '#type' => 'details',
      '#title' => $this->t('Inclusions & exclusions'),
    ];

    $form['base']['containers']['roots'] = [
      '#title' => $this->t("Page areas to check"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => '',
      '#description' => $this->t('<a target="_blank" href="https://developer.mozilla.org/en-US/docs/Learn/CSS/Building_blocks/Selectors">CSS selector</a>, e.g.: <code><em>main, #footer</em></code>'),
      '#default_value' => $config->get('roots'),
    ];

    $form['base']['containers']['hideIcon'] = [
      '#title' => $this->t("Visually hide the icon on these links"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => '',
      '#description' => $this->t(
        'CSS selector, e.g. <code><em>.card a</em></code><br>
            When context makes the link\'s purpose visually obvious, the icon can be visibly hidden using this setting or your CSS: <code><em>.card .link-purpose-icon { display: none; }</em></code>'
      ),
      '#default_value' => $config->get('hideIcon'),
    ];

    $form['base']['containers']['noIconOnImages'] = [
      '#title' => $this->t("Visually hide icons on links with images by default"),
      '#type' => 'checkbox',
      '#placeholder' => '',
      '#description' => $this->t(
        'When enabled, you will need to write CSS to <strong>reveal</strong> the icon span (<code>.link-purpose-icon</code>) on links containing <code>img, svg</code> or <code>figure</code> tags.<br>
        This is not recommended on new sites: noticing and hiding redundant icons is much less error-prone than noticing and revealing missing icons.'
      ),
      '#default_value' => $config->get('noIconOnImages'),
    ];

    $form['base']['containers']['ignore'] = [
      '#title' => $this->t("Ignore these links entirely"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => '',
      '#description' => $this->t(
        'Only use when machine-readable context or the text of the link itself makes the text of the screen reader hint redundant.<br>
                <code><em>#toolbar-administration a</em></code> is recommended, and <code><em>.ck-editor a, .form-textarea-wrapper a</em></code> will always be ignored.<br>
                E.g.: <code><em>#external-links-list a, .in-the-news a, #toolbar-administration a</em></code>.'
      ),
      '#default_value' => $config->get('ignore'),
    ];

    $form['base']['compatibility'] = [
      '#type' => 'details',
      '#title' => $this->t('Module and theme compatibility'),
    ];

    $form['base']['compatibility']['suppressNoBreak'] = [
      '#title' => $this->t("Do not prevent line breaks between last word and icon for these links"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#description' => $this->t('Only recommended if issues with gaps or overflow cannot be fixed in the theme; this makes it likely the icon falls to the next line. Set to "a" to disable behavior altogether; otherwise provide exact matches: <code><em>#child-select a, .direct-select</em></code>.'),
      '#default_value' => $config->get('suppressNoBreak'),
    ];

    $form['base']['compatibility']['insertIconOutsideHiddenSpan'] = [
      '#title' => $this->t("Insert icon outside of these hidden spans"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => '.sr-only, .visually-hidden',
      '#description' => $this->t('Prevents icons from being visually hidden if links already contain screen-reader-only text.<br>Default: <code><em>.sr-only, .visually-hidden</em></code>'),
      '#default_value' => $config->get('insertIconOutsideHiddenSpan'),
    ];

    $form['base']['compatibility']['shadowComponents'] = [
      '#title' => $this->t("Mark links inside this shadow DOM"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => '',
      '#description' => $this->t('<em>Most sites do not have Web components and can ignore this setting.</em><br>E.g.: <code><em>custom-tag-tab-widget, .shadow-embed</em></code>'),
      '#default_value' => $config->get('shadowComponents'),
    ];

    $form['base']['compatibility']['noRunIfPresent'] = [
      '#title' => $this->t("Do not mark any links if elements are detected"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => '',
      '#description' => $this->t('Disables module on page.<br>Default: <code><em>.ck-editor, #quickedit-entity-toolbar, .layout-builder-form</em></code>'),
      '#default_value' => $config->get('noRunIfPresent'),
    ];

    $form['base']['compatibility']['noRunIfAbsent'] = [
      '#title' => $this->t("Do not mark any links if elements are absent"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => '',
      '#default_value' => $config->get('noRunIfAbsent'),
    ];

    $form['base']['compatibility']['themePreprocess'] = [
      '#title' => $this->t("Attach library then stop; theme will mark links"),
      '#type' => 'checkbox',
      '#placeholder' => '',
      '#description' => $this->t(
        'Set if you want to preprocess the options object in theme or module JS, e.g. to add additional link categories or use custom SVG icons.<br>
            Your theme will need a customized copy of <em>linkpurpose-drupal.js</em> to process <em>drupalSettings.linkPurpose</em> and initialize the library.'
      ),
      '#default_value' => $config->get('themePreprocess'),
    ];

    $form['base']['compatibility']['noAggregate'] = [
      '#title' => $this->t("Disable aggregation"),
      '#type' => 'checkbox',
      '#placeholder' => '',
      '#description' => $this->t(
        'Link Purpose is written in ES9. Some aggregation modules only work with ES6.'
      ),
      '#default_value' => $config->get('noAggregate'),
    ];

    $form['base']['migrating'] = [
      '#type' => 'details',
      '#title' => $this->t('Tips for migrating from External Link'),
    ];

    /* @noinspection HtmlUnknownAnchorTarget */
    $form['base']['migrating']['extlink'] = [
      '#markup' => $this->t('<p>If-and-only-if you are migrating from External Link <strong><em>and need near-identical markup</em></strong> to match a theme&rsquo;s existing CSS targeting the external link icons:</p>
                         <ol>
                            <li>Install <a href="https://www.drupal.org/project/fontawesome">FontAwesome</a>. Even if you were not using FontAwesome with ExtLink, it will help you mimic its markup.</li>
                            <li><strong>If you were using SVG icons</strong> in ExtLink, set FontAwesome to embed icons using "SVG with JS." Otherwise, leave your settings as they were.</li>
                            <li>Customize the <a href="#edit-external">external and mailto links</a> to use the "Classes" type.</li>
                            <li>
                                If your theme targeted the .ext or .mailto classes:
                                <ul>
                                    <li>For Mailto links, set "Class added to the &lt;a&gt;" to "<code><em>mailto</em></code>" and add "<code><em>, mailto</em></code>" to the FontAwesome classes in "Icon classes"</li>
                                    <li>For all other link types, set "Class added to the &lt;a&gt;" to "<code><em>ext</em></code>" and add "<code><em>, ext</em></code>" to the FontAwesome classes in "Icon classes"</li>
                                </ul>
                            </li>
                            <li>If things still do not look right, search your theme for "svg" and change it to target one of the classes instead</li>
                         </ol>'
      ),
    ];

    /* Icons fieldset. */
    $form['icons'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Icon categories'),
    ];

    $form['icons']['text'] = [
      '#markup' => $this->t('<p>Links receive the most specific icon: a new-window link to a document will be marked as a document.</p>
            <p>See the <a href="@url" target="_blank">Library docs</a> for output examples.</p>',
            ['@url' => 'https://itmaybejj.github.io/linkpurpose/']),
    ];

    /* External Links. */

    $form['icons']['external'] = [
      '#type' => 'details',
      '#title' => $this->t('External Link Icons'),
    ];

    $form['icons']['external']['purposeExternal'] = [
      '#title' => $this->t("Add icons to links to external sites"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('purposeExternal'),
    ];

    $form['icons']['external']['purposeExternalNoReferrer'] = [
      '#title' => $this->t("External links: add noreferrer attribute"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('purposeExternalNoReferrer'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeexternal"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['icons']['external']['purposeExternalNewWindow'] = [
      '#title' => $this->t("External links: open in new window"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('purposeExternalNewWindow'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeexternal"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['external']['purposeExternalIconType'] = [
      '#title' => $this->t("External links: icon type"),
      '#type' => 'select',
      '#options' => [
        'html' => $this->t('SVG (default)'),
        'classes' => $this->t('Classes (e.g., FontAwesome)'),
      ],
      '#default_value' => $config->get('purposeExternalIconType'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeexternal"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['external']['purposeExternalIconClasses'] = [
      '#title' => $this->t("External links: icon classes"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => 'fa-solid, fa-up-right-from-square',
      '#description' => $this->t('Separate multiple classes with commas. Default: <a href="https://fontawesome.com/icons/arrow-up-right-from-square">fa-solid, fa-arrow-up-right-from-square</a>'),
      '#default_value' => $config->get('purposeExternalIconClasses'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeexternal"]' => ['checked' => TRUE],
          // Enable only when type is classes.
          ':input[id="edit-purposeexternalicontype"]' => ['value' => 'classes'],
        ],
      ],
    ];

    $form['icons']['external']['purposeExternalIconPosition'] = [
      '#title' => $this->t("External links: icon position"),
      '#type' => 'select',
      '#options' => [
        'beforeend' => $this->t('Append (default)'),
        'beforebegin' => $this->t('Before'),
        'afterbegin' => $this->t('Prepend'),
        'afterend' => $this->t('After'),
      ],
      '#default_value' => $config->get('purposeExternalIconPosition'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeexternal"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['external']['purposeExternalMessage'] = [
      '#title' => $this->t("External links: hidden text for screen readers"),
      '#type' => 'textfield',
      '#placeholder' => $this->t('Link is external'),
      '#default_value' => $config->get('purposeExternalMessage'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeexternal"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['external']['purposeExternalClass'] = [
      '#title' => $this->t("External links: class added to the &lt;a&gt; tag"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-external',
      '#default_value' => $config->get('purposeExternalClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeexternal"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['external']['purposeExternalIconWrapperClass'] = [
      '#title' => $this->t("External links: class added to icon wrapper &lt;span&gt;"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-external-icon',
      '#default_value' => $config->get('purposeExternalIconWrapperClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeexternal"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['external']['purposeExternalSelector'] = [
      '#title' => $this->t("External links: selectors for additional links to consider external"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => $this->t('Most sites should not change this.'),
      '#default_value' => $config->get('purposeExternalSelector'),
      '#description' => $this->t('Used for links that have internal HREF attributes, but should be flagged as external anyway.<br>
            Provide a complete CSS selector; be as fancy as you need.<br>
            E.g.: <code><em>.in-the-news a, [href^="https://that-one-subdomain"</em></code>'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeexternal"]' => ['checked' => TRUE],
        ],
      ],
    ];

    /* Document Icons. */

    $form['icons']['document'] = [
      '#type' => 'details',
      '#title' => $this->t('Links to documents'),
    ];

    $form['icons']['document']['purposeDocument'] = [
      '#title' => $this->t("Add icon to links to documents"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('purposeDocument'),
    ];

    $form['icons']['document']['purposeDocumentIconType'] = [
      '#title' => $this->t("Documents: icon type"),
      '#type' => 'select',
      '#options' => [
        'html' => $this->t('SVG (default)'),
        'classes' => $this->t('Classes (e.g., FontAwesome)'),
      ],
      '#default_value' => $config->get('purposeDocumentIconType'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedocument"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['document']['purposeDocumentIconClasses'] = [
      '#title' => $this->t("Documents: icon classes"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => 'fa-regular, fa-file-lines',
      '#description' => $this->t('Separate multiple classes with commas. Default: <a href="https://fontawesome.com/icons/file-lines?f=classic&s=regular">fa-regular, fa-file-lines</a>'),
      '#default_value' => $config->get('purposeDocumentIconClasses'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedocument"]' => ['checked' => TRUE],
          // Enable only when type is classes.
          ':input[id="edit-purposedocumenticontype"]' => ['value' => 'classes'],
        ],
      ],
    ];

    $form['icons']['document']['purposeDocumentIconPosition'] = [
      '#title' => $this->t("Documents: icon position"),
      '#type' => 'select',
      '#options' => [
        'beforeend' => $this->t('Append (default)'),
        'beforebegin' => $this->t('Before'),
        'afterbegin' => $this->t('Prepend'),
        'afterend' => $this->t('After'),
      ],
      '#default_value' => $config->get('purposeDocumentIconPosition'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedocument"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['document']['purposeDocumentMessage'] = [
      '#title' => $this->t("Documents: hidden text for screen readers"),
      '#type' => 'textfield',
      '#placeholder' => $this->t('Link downloads document'),
      '#default_value' => $config->get('purposeDocumentMessage'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedocument"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['document']['purposeDocumentClass'] = [
      '#title' => $this->t("Documents: class added to the &lt;a&gt; tag"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-document',
      '#default_value' => $config->get('purposeDocumentClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedocument"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['document']['purposeDocumentIconWrapperClass'] = [
      '#title' => $this->t("Documents: class added to icon wrapper &lt;span&gt;"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-document-icon',
      '#default_value' => $config->get('purposeDocumentIconWrapperClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedocument"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['document']['purposeDocumentSelector'] = [
      '#title' => $this->t("Documents: CSS selector"),
      '#type' => 'textarea',
      '#placeholder' => "[href^='/document/'], [href$='.pdf'], [href*='.pdf?'], [href$='.doc'], [href$='.docx'], [href*='.doc?'], [href*='.docx?'], [href$='.ppt'], [href$='.pptx'], [href*='.ppt?'], [href*='.pptx?'], [href^='https://docs.google']",
      '#default_value' => $config->get('purposeDocumentSelector'),
      '#description' => $this->t("Add or remove categories to flag. Separate multiple attributes with commas.<br>Default: <br><code><em>[href^='/document/'], [href$='.pdf'], [href*='.pdf?'], [href$='.doc'], [href$='.docx'], [href*='.doc?'], [href*='.docx?'], [href$='.ppt'], [href$='.pptx'], [href*='.ppt?'], [href*='.pptx?'], [href^='https://docs.google']</em></code>"),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedocument"]' => ['checked' => TRUE],
        ],
      ],
    ];

    /* Download Links. */

    $form['icons']['download'] = [
      '#type' => 'details',
      '#title' => $this->t('Links that download files'),
    ];

    $form['icons']['download']['text'] = [
      '#markup' => $this->t('Note that this category "loses" to documents -- this is for non-document file downloads with a "download" attribute on the link.'),
    ];

    $form['icons']['download']['purposeDownload'] = [
      '#title' => $this->t("Add icon to links that download files"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('purposeDownload'),
    ];

    $form['icons']['download']['purposeDownloadIconType'] = [
      '#title' => $this->t("Download links: icon type"),
      '#type' => 'select',
      '#options' => [
        'html' => $this->t('SVG (default)'),
        'classes' => $this->t('Classes (e.g., FontAwesome)'),
      ],
      '#default_value' => $config->get('purposeDownloadIconType'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedownload"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['download']['purposeDownloadIconClasses'] = [
      '#title' => $this->t("Download links: icon classes"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => 'fa-solid, fa-download',
      '#description' => $this->t('Separate multiple classes with commas. Default: <a href="https://fontawesome.com/icons/download">fa-solid, fa-download</a>'),
      '#default_value' => $config->get('purposeDownloadIconClasses'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedownload"]' => ['checked' => TRUE],
          // Enable only when type is classes.
          ':input[id="edit-purposedownloadicontype"]' => ['value' => 'classes'],
        ],
      ],
    ];

    $form['icons']['download']['purposeDownloadIconPosition'] = [
      '#title' => $this->t("Download links: icon position"),
      '#type' => 'select',
      '#options' => [
        'beforeend' => $this->t('Append (default)'),
        'beforebegin' => $this->t('Before'),
        'afterbegin' => $this->t('Prepend'),
        'afterend' => $this->t('After'),
      ],
      '#default_value' => $config->get('purposeDownloadIconPosition'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedownload"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['download']['purposeDownloadMessage'] = [
      '#title' => $this->t("Download links: hidden text for screen readers"),
      '#type' => 'textfield',
      '#placeholder' => $this->t('Link downloads file'),
      '#default_value' => $config->get('purposeDownloadMessage'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedownload"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['download']['purposeDownloadClass'] = [
      '#title' => $this->t("Download links: class added to the &lt;a&gt; tag"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-download',
      '#default_value' => $config->get('purposeDownloadClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedownload"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['download']['purposeDownloadIconWrapperClass'] = [
      '#title' => $this->t("Download links: class added to icon wrapper &lt;span&gt;"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-download-icon',
      '#default_value' => $config->get('purposeDownloadIconWrapperClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedownload"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['download']['purposeDownloadSelector'] = [
      '#title' => $this->t("Download links: selectors for links to consider external"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => $this->t('Most sites should not change this.'),
      '#default_value' => $config->get('purposeDownloadSelector'),
      '#description' => $this->t('Set if you want to flag some links <em>without</em> a download attribute.<br>
            E.g.: <code><em>[download], [href$=".zip"]</em></code>'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposedownload"]' => ['checked' => TRUE],
        ],
      ],
    ];

    /* Links that open applications. */

    $form['icons']['app'] = [
      '#type' => 'details',
      '#title' => $this->t('Links that open applications'),
    ];

    $form['icons']['app']['purposeApp'] = [
      '#title' => $this->t("Add icon to links that open applications"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('purposeApp'),
    ];

    $form['icons']['app']['purposeAppIconType'] = [
      '#title' => $this->t("App links: icon type"),
      '#type' => 'select',
      '#options' => [
        'html' => $this->t('SVG (default)'),
        'classes' => $this->t('Classes (e.g., FontAwesome)'),
      ],
      '#default_value' => $config->get('purposeAppIconType'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeapp"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['app']['purposeAppIconClasses'] = [
      '#title' => $this->t("App links: icon classes"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => 'fa-solid, fa-window-restore',
      '#description' => $this->t('Separate multiple classes with commas. Default: <a href="https://fontawesome.com/icons/download">fa-solid, fa-window-restore</a>'),
      '#default_value' => $config->get('purposeDownloadIconClasses'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeapp"]' => ['checked' => TRUE],
          // Enable only when type is classes.
          ':input[id="edit-purposeappicontype"]' => ['value' => 'classes'],
        ],
      ],
    ];

    $form['icons']['app']['purposeAppIconPosition'] = [
      '#title' => $this->t("App links: icon position"),
      '#type' => 'select',
      '#options' => [
        'beforeend' => $this->t('Append (default)'),
        'beforebegin' => $this->t('Before'),
        'afterbegin' => $this->t('Prepend'),
        'afterend' => $this->t('After'),
      ],
      '#default_value' => $config->get('purposeAppIconPosition'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeapp"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['app']['purposeAppMessage'] = [
      '#title' => $this->t("App links: hidden text for screen readers"),
      '#type' => 'textfield',
      '#placeholder' => $this->t('Link opens app'),
      '#default_value' => $config->get('purposeAppMessage'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeapp"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['app']['purposeAppClass'] = [
      '#title' => $this->t("App links: class added to the &lt;a&gt; tag"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-app',
      '#default_value' => $config->get('purposeAppClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeapp"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['app']['purposeAppIconWrapperClass'] = [
      '#title' => $this->t("App links: class added to icon wrapper &lt;span&gt;"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-app-icon',
      '#default_value' => $config->get('purposeAppIconWrapperClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeapp"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['app']['purposeAppSelector'] = [
      '#title' => $this->t("App links: selector"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => $this->t('Most sites should not change this.'),
      '#default_value' => $config->get('purposeAppSelector'),
      '#description' => $this->t('Modify to map specific protocols out of the not().<br>
            Default: <code><em>:is([href*="://"]):not([href^="https:"], [href^="http:"], [href^="file:"])</em></code>'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposeapp"]' => ['checked' => TRUE],
        ],
      ],
    ];

    /* Mailto Icons. */

    $form['icons']['mail'] = [
      '#type' => 'details',
      '#title' => $this->t('Links to email addresses'),
    ];

    $form['icons']['mail']['purposeMail'] = [
      '#title' => $this->t("Add icon to links to email addresses"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('purposeMail'),
    ];

    $form['icons']['mail']['purposeMailIconType'] = [
      '#title' => $this->t("Email links: icon type"),
      '#type' => 'select',
      '#options' => [
        'html' => $this->t('SVG (default)'),
        'classes' => $this->t('Classes (e.g., FontAwesome)'),
      ],
      '#default_value' => $config->get('purposeMailIconType'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposemail"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['mail']['purposeMailIconClasses'] = [
      '#title' => $this->t("Email links: icon classes"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => 'fa-solid, fa-up-right-from-square',
      '#description' => $this->t('Separate multiple classes with commas. Default: <a href="https://fontawesome.com/icons/arrow-up-right-from-square">fa-solid, fa-arrow-up-right-from-square</a>'),
      '#default_value' => $config->get('purposeMailIconClasses'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposemail"]' => ['checked' => TRUE],
          // Enable only when type is classes.
          ':input[id="edit-purposemailicontype"]' => ['value' => 'classes'],
        ],
      ],
    ];

    $form['icons']['mail']['purposeMailIconPosition'] = [
      '#title' => $this->t("Email links: icon Position"),
      '#type' => 'select',
      '#options' => [
        'beforeend' => $this->t('Append (default)'),
        'beforebegin' => $this->t('Before'),
        'afterbegin' => $this->t('Prepend'),
        'afterend' => $this->t('After'),
      ],
      '#default_value' => $config->get('purposeMailIconPosition'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposemail"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['mail']['purposeMailMessage'] = [
      '#title' => $this->t("Email links: hidden text for screen readers"),
      '#type' => 'textfield',
      '#placeholder' => $this->t('Link sends email'),
      '#default_value' => $config->get('purposeMailMessage'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposemail"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['mail']['purposeMailClass'] = [
      '#title' => $this->t("Email links: class added to the &lt;a&gt;"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-mail',
      '#default_value' => $config->get('purposeMailClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposemail"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['mail']['purposeMailIconWrapperClass'] = [
      '#title' => $this->t("Email links: class added to icon wrapper &lt;span&gt;"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-mail-icon',
      '#default_value' => $config->get('purposeMailIconWrapperClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposemail"]' => ['checked' => TRUE],
        ],
      ],
    ];

    /* Telephone Icons. */

    $form['icons']['tel'] = [
      '#type' => 'details',
      '#title' => $this->t('Telephone links'),
    ];

    $form['icons']['tel']['purposeTel'] = [
      '#title' => $this->t("Add icon to telephone links"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('purposeTel'),
    ];

    $form['icons']['tel']['purposeTelIconType'] = [
      '#title' => $this->t("Phone links: icon type"),
      '#type' => 'select',
      '#options' => [
        'html' => $this->t('SVG (default)'),
        'classes' => $this->t('Classes (e.g., FontAwesome)'),
      ],
      '#default_value' => $config->get('purposeTelIconType'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposetel"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['tel']['purposeTelIconClasses'] = [
      '#title' => $this->t("Phone links: icon classes"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => '',
      '#description' => $this->t('Separate multiple classes with commas. Default: <a href="https://fontawesome.com/icons/square-phone">fa-solid, fa-square-phone</a>'),
      '#default_value' => $config->get('purposeTelIconClasses'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposetel"]' => ['checked' => TRUE],
          // Enable only when type is classes.
          ':input[id="edit-purposetelicontype"]' => ['value' => 'classes'],
        ],
      ],
    ];

    $form['icons']['tel']['purposeTelIconPosition'] = [
      '#title' => $this->t("Phone links: icon type"),
      '#type' => 'select',
      '#options' => [
        'beforeend' => $this->t('Append (default)'),
        'beforebegin' => $this->t('Before'),
        'afterbegin' => $this->t('Prepend'),
        'afterend' => $this->t('After'),
      ],
      '#default_value' => $config->get('purposeTelIconPosition'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposetel"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['tel']['purposeTelMessage'] = [
      '#title' => $this->t("Phone links: hidden text for screen readers"),
      '#type' => 'textfield',
      '#placeholder' => $this->t('Link opens phone'),
      '#default_value' => $config->get('purposeTelMessage'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposetel"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['tel']['purposeTelClass'] = [
      '#title' => $this->t("Phone links: class added to the &lt;a&gt;"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-tel',
      '#default_value' => $config->get('purposeTelClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposetel"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['tel']['purposeTelIconWrapperClass'] = [
      '#title' => $this->t("Phone links: class added to icon wrapper &lt;span&gt;"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-tel-icon',
      '#default_value' => $config->get('purposeTelIconWrapperClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposetel"]' => ['checked' => TRUE],
        ],
      ],
    ];

    /* New Window Icons. */
    $form['icons']['window'] = [
      '#type' => 'details',
      '#title' => $this->t('Links that open in a new window'),
    ];

    $form['icons']['window']['purposeNewWindow'] = [
      '#title' => $this->t("Add icon to links that open a new window"),
      '#type' => 'checkbox',
      '#default_value' => $config->get('purposeNewWindow'),
    ];

    $form['icons']['window']['purposeNewWindowIconType'] = [
      '#title' => $this->t("New window links: icon type"),
      '#type' => 'select',
      '#options' => [
        'html' => $this->t('SVG (default)'),
        'classes' => $this->t('Classes (e.g., FontAwesome)'),
      ],
      '#default_value' => $config->get('purposeNewWindowIconType'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposenewwindow"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['window']['purposeNewWindowIconClasses'] = [
      '#title' => $this->t("New window links: icon classes"),
      '#type' => 'textarea',
      '#rows' => 1,
      '#placeholder' => 'fa-regular, fa-window-restore',
      '#description' => $this->t('Separate multiple classes with commas. Default: <a href="https://fontawesome.com/icons/window-restore?f=classic&s=regular">fa-solid, fa-arrow-up-right-from-square</a>'),
      '#default_value' => $config->get('purposeNewWindowIconClasses'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposenewwindow"]' => ['checked' => TRUE],
          // Enable only when type is classes.
          ':input[id="edit-purposenewwindowicontype"]' => ['value' => 'classes'],
        ],
      ],
    ];

    $form['icons']['window']['purposeNewWindowIconPosition'] = [
      '#title' => $this->t("New window links: icon position"),
      '#type' => 'select',
      '#options' => [
        'beforeend' => $this->t('Append (default)'),
        'beforebegin' => $this->t('Before'),
        'afterbegin' => $this->t('Prepend'),
        'afterend' => $this->t('After'),
      ],
      '#default_value' => $config->get('purposeNewWindowIconPosition'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposenewwindow"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['window']['purposeNewWindowMessage'] = [
      '#title' => $this->t("New window links: hidden text for screen readers"),
      '#type' => 'textfield',
      '#placeholder' => $this->t('Link opens in new window'),
      '#default_value' => $config->get('purposeNewWindowMessage'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposenewwindow"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['window']['purposeNewWindowClass'] = [
      '#title' => $this->t("New window links: class added to the &lt;a&gt;"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-window',
      '#default_value' => $config->get('purposeNewWindowClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposenewwindow"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['icons']['window']['purposeNewWindowIconWrapperClass'] = [
      '#title' => $this->t("New window links: class added to icon wrapper &lt;span&gt;"),
      '#type' => 'textfield',
      '#placeholder' => 'link-purpose-window-icon',
      '#default_value' => $config->get('purposeNewWindowIconWrapperClass'),
      '#states' => [
        'visible' => [
          ':input[id="edit-purposenewwindow"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('linkpurpose.settings')
      ->set('roots', $form_state->getValue('roots'))
      ->set('domain', $form_state->getValue('domain'))
      ->set('shadowComponents', $form_state->getValue('shadowComponents'))
      ->set('ignore', $form_state->getValue('ignore'))
      ->set('hideIcon', $form_state->getValue('hideIcon'))
      ->set('noRunIfPresent', $form_state->getValue('noRunIfPresent'))
      ->set('noRunIfAbsent', $form_state->getValue('noRunIfAbsent'))
      ->set('themePreprocess', $form_state->getValue('themePreprocess'))
      ->set('noAggregate', $form_state->getValue('noAggregate'))
      ->set('noIconOnImages', $form_state->getValue('noIconOnImages'))
      ->set('suppressNoBreak', $form_state->getValue('suppressNoBreak'))
      ->set('insertIconOutsideHiddenSpan', $form_state->getValue('insertIconOutsideHiddenSpan'))

      ->set('purposeDocument', $form_state->getValue('purposeDocument'))
      ->set('purposeDocumentSelector', $form_state->getValue('purposeDocumentSelector'))
      ->set('purposeDocumentMessage', $form_state->getValue('purposeDocumentMessage'))
      ->set('purposeDocumentClass', $form_state->getValue('purposeDocumentClass'))
      ->set('purposeDocumentIconWrapperClass', $form_state->getValue('purposeDocumentIconWrapperClass'))
      ->set('purposeDocumentIconType', $form_state->getValue('purposeDocumentIconType'))
      ->set('purposeDocumentIconPosition', $form_state->getValue('purposeDocumentIconPosition'))
      ->set('purposeDocumentIconClasses', $form_state->getValue('purposeDocumentIconClasses'))

      ->set('purposeDownload', $form_state->getValue('purposeDownload'))
      ->set('purposeDownloadSelector', $form_state->getValue('purposeDownloadSelector'))
      ->set('purposeDownloadMessage', $form_state->getValue('purposeDownloadMessage'))
      ->set('purposeDownloadClass', $form_state->getValue('purposeDownloadClass'))
      ->set('purposeDownloadIconWrapperClass', $form_state->getValue('purposeDownloadIconWrapperClass'))
      ->set('purposeDownloadIconType', $form_state->getValue('purposeDownloadIconType'))
      ->set('purposeDownloadIconPosition', $form_state->getValue('purposeDownloadIconPosition'))
      ->set('purposeDownloadIconClasses', $form_state->getValue('purposeDownloadIconClasses'))

      ->set('purposeApp', $form_state->getValue('purposeApp'))
      ->set('purposeAppSelector', $form_state->getValue('purposeAppSelector'))
      ->set('purposeAppMessage', $form_state->getValue('purposeAppMessage'))
      ->set('purposeAppClass', $form_state->getValue('purposeAppClass'))
      ->set('purposeAppIconWrapperClass', $form_state->getValue('purposeAppIconWrapperClass'))
      ->set('purposeAppIconType', $form_state->getValue('purposeAppIconType'))
      ->set('purposeAppIconPosition', $form_state->getValue('purposeAppIconPosition'))
      ->set('purposeAppIconClasses', $form_state->getValue('purposeAppIconClasses'))

      ->set('purposeMail', $form_state->getValue('purposeMail'))
      ->set('purposeMailSelector', $form_state->getValue('purposeMailSelector'))
      ->set('purposeMailMessage', $form_state->getValue('purposeMailMessage'))
      ->set('purposeMailClass', $form_state->getValue('purposeMailClass'))
      ->set('purposeMailIconWrapperClass', $form_state->getValue('purposeMailIconWrapperClass'))
      ->set('purposeMailIconType', $form_state->getValue('purposeMailIconType'))
      ->set('purposeMailIconPosition', $form_state->getValue('purposeMailIconPosition'))
      ->set('purposeMailIconClasses', $form_state->getValue('purposeMailIconClasses'))

      ->set('purposeTel', $form_state->getValue('purposeTel'))
      ->set('purposeTelSelector', $form_state->getValue('purposeTelSelector'))
      ->set('purposeTelMessage', $form_state->getValue('purposeTelMessage'))
      ->set('purposeTelClass', $form_state->getValue('purposeTelClass'))
      ->set('purposeTelIconWrapperClass', $form_state->getValue('purposeTelIconWrapperClass'))
      ->set('purposeTelIconType', $form_state->getValue('purposeTelIconType'))
      ->set('purposeTelIconPosition', $form_state->getValue('purposeTelIconPosition'))
      ->set('purposeTelIconClasses', $form_state->getValue('purposeTelIconClasses'))

      ->set('purposeNewWindow', $form_state->getValue('purposeNewWindow'))
      ->set('purposeNewWindowSelector', $form_state->getValue('purposeNewWindowSelector'))
      ->set('purposeNewWindowMessage', $form_state->getValue('purposeNewWindowMessage'))
      ->set('purposeNewWindowClass', $form_state->getValue('purposeNewWindowClass'))
      ->set('purposeNewWindowIconWrapperClass', $form_state->getValue('purposeNewWindowIconWrapperClass'))
      ->set('purposeNewWindowIconType', $form_state->getValue('purposeNewWindowIconType'))
      ->set('purposeNewWindowIconPosition', $form_state->getValue('purposeNewWindowIconPosition'))
      ->set('purposeNewWindowIconClasses', $form_state->getValue('purposeNewWindowIconClasses'))

      ->set('purposeExternal', $form_state->getValue('purposeExternal'))
      ->set('purposeExternalSelector', $form_state->getValue('purposeExternalSelector'))
      ->set('purposeExternalMessage', $form_state->getValue('purposeExternalMessage'))
      ->set('purposeExternalClass', $form_state->getValue('purposeExternalClass'))
      ->set('purposeExternalNoReferrer', $form_state->getValue('purposeExternalNoReferrer'))
      ->set('purposeExternalNewWindow', $form_state->getValue('purposeExternalNewWindow'))
      ->set('purposeExternalIconWrapperClass', $form_state->getValue('purposeExternalIconWrapperClass'))
      ->set('purposeExternalIconType', $form_state->getValue('purposeExternalIconType'))
      ->set('purposeExternalIconPosition', $form_state->getValue('purposeExternalIconPosition'))
      ->set('purposeExternalIconClasses', $form_state->getValue('purposeExternalIconClasses'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
