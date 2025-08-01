<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Plugin\AiContentSuggestions;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_content_suggestions\AiContentSuggestionsPluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the ai_content_suggestions.
 *
 * @AiContentSuggestions(
 *   id = "title_suggest",
 *   label = @Translation("Suggest title"),
 *   description = @Translation("Allow an LLM to suggest an SEO-friendly title for the content."),
 *   operation_type = "chat"
 * )
 */
final class Title extends AiContentSuggestionsPluginBase {

  /**
   * The Default prompt for this functionality.
   *
   * @var string
   */
  private string $defaultPrompt = 'Suggest an SEO friendly title for this page based off of the following content in 10 words or less, in the same language as the input:';

  /**
   * Configuration object for this plugin.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $promptConfig;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AiProviderPluginManager $providerPluginManager,
    ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $providerPluginManager, $configFactory);

    $this->promptConfig = $configFactory->getEditable('ai_content_suggestions.prompts');
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, array $fields): void {
    $form[$this->getPluginId()] = $this->getAlterFormTemplate($fields);
    $form[$this->getPluginId()][$this->getPluginId() . '_submit']['#value'] = $this->t('Suggest title');
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(&$form): void {
    parent::buildSettingsForm($form);
    $prompt = $this->promptConfig->get($this->getPluginId());
    $form[$this->getPluginId()][$this->getPluginId() . '_prompt'] = [
      '#title' => $this->t('Suggest Title prompt', []),
      '#type' => 'textarea',
      '#required' => TRUE,
      '#default_value' => $prompt ?? $this->defaultPrompt . PHP_EOL,
      '#parents' => ['plugins', $this->getPluginId(), $this->getPluginId() . '_prompt'],
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getPluginId() . '[' . $this->getPluginId() . '_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function saveSettingsForm(array &$form, FormStateInterface $form_state): void {
    $value = $form_state->getValue(['plugins', $this->getPluginId()]);
    $prompt = $value[$this->getPluginId() . '_prompt'];
    $this->promptConfig->set($this->getPluginId(), $prompt)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function updateFormWithResponse(array &$form, FormStateInterface $form_state): void {
    $title_prompt = $this->promptConfig->get($this->getPluginId());
    if (!empty($title_prompt)) {
      $prompt = $title_prompt;
    }
    else {
      $prompt = $this->defaultPrompt . '\r\n"';
    }

    if ($value = $this->getTargetFieldValue($form_state)) {
      $message = $this->sendChat($prompt . $value . '"');
    }
    else {
      $message = $this->t('The selected field has no text. Please supply content to the field.');
    }

    $form[$this->getPluginId()]['response']['response']['#context']['response']['response'] = [
      '#markup' => $message,
      '#weight' => 100,
    ];
  }

}
