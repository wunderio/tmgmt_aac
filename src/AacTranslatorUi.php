<?php

namespace Drupal\tmgmt_aac;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\SourcePreviewInterface;
use Drupal\tmgmt\TranslatorPluginUiBase;

/**
 * AAC Global translator UI.
 */
class AacTranslatorUi extends TranslatorPluginUiBase {
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();

    $form['username'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Username'),
      '#default_value' => $translator->getSetting('username'),
      '#description' => $this->t('Please enter your username or visit <a href="@url">My AAC Global</a> to get one.', ['@url' => 'https://my.aacglobal.com']),
    ];
    $form['password'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Password'),
      '#default_value' => $translator->getSetting('password'),
      '#description' => $this->t('Please enter your password or visit <a href="@url">My AAC Global</a> to get one.', ['@url' => 'https://my.aacglobal.com']),
    ];
    $form += parent::addConnectButton();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();
    if ($translator->getSetting('username') && $translator->getSetting('password')) {
      $account = $translator->getPlugin()->getAccount($translator);
      if (empty($account)) {
        $form_state->setError($form['plugin_wrapper']['settings'], t('Connection failed. Please check username and password.'));
      }
    }
  }

}
