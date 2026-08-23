<?php

namespace Drupal\rules\Context;

use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Defines an interface for the execution context state.
 */
interface ExecutionStateInterface {

  /**
   * Sets a state variable based on its definition and value.
   *
   * @param string $name
   *   The context variable name.
   * @param \Drupal\rules\Context\ContextDefinitionInterface $definition
   *   The context definition of the variable.
   * @param mixed $value
   *   The variable value.
   *
   * @return $this
   */
  public function setVariable(string $name, ContextDefinitionInterface $definition, $value): static;

  /**
   * Sets a state variable with some typed data object.
   *
   * @param string $name
   *   The variable name.
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   The variable wrapped as typed data.
   *
   * @return $this
   */
  public function setVariableData(string $name, TypedDataInterface $data): static;

  /**
   * Gets a variable.
   *
   * @param string $name
   *   The name of the variable to return.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The variable wrapped as typed data.
   *
   * @throws \Drupal\rules\Exception\EvaluationException
   *   Throws an EvaluationException if the variable does not exist in the
   *   state.
   */
  public function getVariable(string $name): TypedDataInterface;

  /**
   * Gets the value of a variable.
   *
   * @param string $name
   *   The name of the variable to return the value for.
   *
   * @return mixed
   *   The variable value.
   *
   * @throws \Drupal\rules\Exception\EvaluationException
   *   Throws an EvaluationException if the variable does not exist in the
   *   state.
   */
  public function getVariableValue(string $name);

  /**
   * Checks if a variable exists by name in the execution state.
   *
   * @param string $name
   *   The variable name.
   *
   * @return bool
   *   TRUE if the variable exists, FALSE otherwise.
   */
  public function hasVariable(string $name): bool;

  /**
   * Removes a variable from the state if it is set.
   *
   * @param string $name
   *   The name of the variable to remove.
   *
   * @return $this
   */
  public function removeVariable(string $name): static;

  /**
   * Returns a value as specified in the selector.
   *
   * @param string $property_path
   *   The property path string starting with a variable name; e.g.,
   *   "node.uid.entity.mail.value".
   * @param string $langcode
   *   (optional) The language code used to get the argument value if the
   *   argument value should be translated. Defaults to NULL.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The variable wrapped as typed data.
   *
   * @throws \Drupal\rules\Exception\EvaluationException
   *   Throws an EvaluationException in case the selector cannot be applied.
   */
  public function fetchDataByPropertyPath(string $property_path, ?string $langcode = NULL): TypedDataInterface;

  /**
   * Mark a variable to be saved later when the execution is finished.
   *
   * @param string $selector
   *   The data selector that specifies the target object to be saved. Example:
   *   node.uid.entity.
   *
   * @return $this
   */
  public function saveChangesLater(string $selector): static;

  /**
   * Returns the list of variables that should be auto-saved after execution.
   *
   * @return string[]
   *   The list of data selectors that specify the target object to be saved.
   *   Example: node.uid.entity.
   */
  public function getAutoSaveSelectors(): array;

  /**
   * Saves all variables that have been marked for auto saving.
   *
   * @return $this
   */
  public function autoSave(): static;

}
