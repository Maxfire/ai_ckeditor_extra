<?php

namespace Drupal\ai_ckeditor_extra\Plugin\AICKEditor;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\ai_ckeditor\Command\AiRequestCommand;
use Drupal\taxonomy\Entity\Term;

/**
 * Plugin to extract the external references of selected text.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_extra_external_ref',
  label: new TranslatableMarkup('External Referencies'),
  description: new TranslatableMarkup('Extract the external references of selected text.'),
)]
final class ExternalRefs extends AiCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'provider' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = $this->aiProviderManager->getSimpleProviderModelOptions('chat');
    array_shift($options);
    array_splice($options, 0, 1);
    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('AI provider'),
      '#options' => $options,
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
      '#default_value' => $this->configuration['provider'] ?? $this->aiProviderManager->getSimpleDefaultProviderOptions('chat'),
      '#description' => $this->t('Select which provider to use for this plugin. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['provider'] = $form_state->getValue('provider');
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $editor_id = $this->requestStack->getParentRequest()->get('editor_id');

    if (empty($storage['selected_text'])) {
      return [
        '#markup' => '<p>' . $this->t('You must select some text before you can get the references.') . '</p>',
      ];
    }

    $form = parent::buildCkEditorModalForm($form, $form_state);

    $form['selected_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Selected text to get the references'),
      '#disabled' => TRUE,
      '#default_value' => $storage['selected_text'],
    ];

    $form['response_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Get references'),
      '#description' => $this->t('The response from AI will appear in the box above. You can edit and tweak the response before saving it back to the main editor.'),
      '#prefix' => '<div id="ai-ckeditor-response">',
      '#suffix' => '</div>',
      '#default_value' => '',
      '#allowed_formats' => [$editor_id],
      '#format' => $editor_id,
    ];

    $form['actions']['generate']['#value'] = $this->t('Get references');

    return $form;
  }

  /**
   * Generate text callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The result of the AJAX operation.
   */
  public function ajaxGenerate(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    try {
      $prompt = 'Do not modify the provided text but only add the links. Append the external references and links found and show them at the bottom under the text "References" for the following text :\r\n"' . $values["plugin_config"]["selected_text"];
      $response = new AjaxResponse();
      $values = $form_state->getValues();
      $response->addCommand(new AiRequestCommand($prompt, $values["editor_id"], $this->pluginDefinition['id'], 'ai-ckeditor-response'));
      return $response;
    }
    catch (\Exception $e) {
      $this->logger->error("There was an error in the scoring AI plugin for CKEditor.");
      return $form['plugin_config']['response_text']['#value'] = "There was an error in the scoring AI plugin for CKEditor.";
    }
  }

}
