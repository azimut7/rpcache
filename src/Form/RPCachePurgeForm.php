<?php

namespace Drupal\rpcache\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\redis\ClientFactory;

/**
 * Class RPCacheRemoveForm.
 */
class RPCachePurgeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rpcache_remove_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of URLs for purging from the Redis Page Cache:'),
      '#description' => $this->t('One URL per line. After that all these pages will be handled by Drupal Page Cache until you rebuild Drupal cache.'),
      '#prefix' => $this->t('It does not affect the standard Drupal cache entries.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Purge URLs'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url_list = trim($form_state->getValue('urls'));
    $url_list = explode("\n", str_replace("\r", "", $url_list));

    if (ClientFactory::hasClient()) {
      $prefix = \Drupal::config('rpcache.settings')->get('prefix');
      $this->redis = ClientFactory::getClient();
      foreach ($url_list as $url) {
        $key = $prefix . $url;
        $this->redis->del($key);
        drupal_set_message($this->t('Key "@key" was sent to Redis for removing.', ['@key' => $key]));
      }
    }
    else {
      throw new \Exception("Redis client is not found. Is Redis module enabled and configured?");
    }
  }

}
