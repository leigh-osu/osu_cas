<?php

declare(strict_types=1);

namespace Drupal\paragraph_view_mode\Checker;

use Drupal\paragraphs\ParagraphInterface;

/**
 * The widget settings checker interface.
 */
interface WidgetSettingsCheckerInterface {

  /**
   * Checks if the form mode bind feature is enabled for the given paragraph.
   *
   * @param string $form_mode
   *   The form mode.
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return bool
   *   TRUE in case the bind with form mode is enabled, FALSE otherwise.
   */
  public function hasFormModeBindEnabled(
    string $form_mode,
    ParagraphInterface $paragraph,
  ): bool;

  /**
   * Checks if the apply to previews feature is enabled for the given paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return bool
   *   TRUE in case applying to preview is enabled, FALSE otherwise.
   */
  public function hasApplyToPreviewEnabled(ParagraphInterface $paragraph): bool;

}
