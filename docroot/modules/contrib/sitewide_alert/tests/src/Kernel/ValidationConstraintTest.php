<?php

declare(strict_types=1);

namespace Drupal\Tests\sitewide_alert\Kernel;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\sitewide_alert\Entity\SitewideAlert;

/**
 * Tests validation constraints for SitewideAlert entities.
 *
 * @group sitewide_alert
 */
final class ValidationConstraintTest extends SitewideAlertKernelTestBase {

  /**
   * Creates an unsaved alert entity for validation testing.
   *
   * @param array $values
   *   Entity field values.
   *
   * @return \Drupal\sitewide_alert\Entity\SitewideAlert
   *   The unsaved entity.
   */
  protected function createUnsavedAlert(array $values = []): SitewideAlert {
    $alert = SitewideAlert::create($values + [
      'status' => 1,
      'user_id' => 1,
      'name' => $this->randomMachineName(),
      'style' => 'primary',
      'dismissible' => TRUE,
      'message' => [
        'value' => 'Test message',
        'format' => 'plain_text',
      ],
    ]);
    return $alert;
  }

  /**
   * Tests scheduled alert without dates fails validation.
   */
  public function testScheduledWithoutDatesFails(): void {
    $alert = $this->createUnsavedAlert([
      'scheduled_alert' => TRUE,
    ]);

    $violations = $alert->validate();
    $this->assertGreaterThan(0, $violations->count());

    $found = FALSE;
    foreach ($violations as $violation) {
      if (str_contains((string) $violation->getMessage(), 'scheduled dates are not provided')) {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found, 'Expected ScheduledDateProvided violation not found.');
  }

  /**
   * Tests scheduled alert with valid dates passes validation.
   */
  public function testScheduledWithDatesPasses(): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $alert = $this->createUnsavedAlert([
      'scheduled_alert' => TRUE,
      'scheduled_date' => [
        'value' => $now->modify('-1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $now->modify('+1 hour')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ]);

    $violations = $alert->validate();
    $scheduledViolations = 0;
    foreach ($violations as $violation) {
      if (str_contains((string) $violation->getMessage(), 'scheduled dates are not provided')) {
        $scheduledViolations++;
      }
    }
    $this->assertEquals(0, $scheduledViolations);
  }

  /**
   * Tests non-scheduled alert without dates passes validation.
   */
  public function testNotScheduledWithoutDatesPasses(): void {
    $alert = $this->createUnsavedAlert([
      'scheduled_alert' => FALSE,
    ]);

    $violations = $alert->validate();
    $scheduledViolations = 0;
    foreach ($violations as $violation) {
      if (str_contains((string) $violation->getMessage(), 'scheduled dates are not provided')) {
        $scheduledViolations++;
      }
    }
    $this->assertEquals(0, $scheduledViolations);
  }

  /**
   * Tests limit_to_pages without leading slash fails validation.
   */
  public function testLimitToPagesWithoutLeadingSlashFails(): void {
    $alert = $this->createUnsavedAlert([
      'limit_to_pages' => 'no-leading-slash',
    ]);

    $violations = $alert->validate();
    $found = FALSE;
    foreach ($violations as $violation) {
      if (str_contains((string) $violation->getMessage(), 'limit by page path(s) are invalid')) {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found, 'Expected LimitToPages violation not found.');
  }

  /**
   * Tests limit_to_pages with valid paths passes validation.
   */
  public function testLimitToPagesWithValidPathsPasses(): void {
    $alert = $this->createUnsavedAlert([
      'limit_to_pages' => "/valid/path\n/another/path",
    ]);

    $violations = $alert->validate();
    $pathViolations = 0;
    foreach ($violations as $violation) {
      if (str_contains((string) $violation->getMessage(), 'limit by page path(s) are invalid')) {
        $pathViolations++;
      }
    }
    $this->assertEquals(0, $pathViolations);
  }

  /**
   * Tests limit_to_pages with empty string passes validation.
   */
  public function testLimitToPagesEmptyStringPasses(): void {
    $alert = $this->createUnsavedAlert([
      'limit_to_pages' => '',
    ]);

    $violations = $alert->validate();
    $pathViolations = 0;
    foreach ($violations as $violation) {
      if (str_contains((string) $violation->getMessage(), 'limit by page path(s) are invalid')) {
        $pathViolations++;
      }
    }
    $this->assertEquals(0, $pathViolations);
  }

}
