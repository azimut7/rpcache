<?php

namespace Drupal\rpcache\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\redis\ClientFactory;

/**
 * Class RPCacheRemoveForm.
 */
class RPCacheClearAllForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rpcache_clear_all_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['info'] = [
      '#markup' => '<p>'. $this->t('Clear all Redis Page Cache entries. It does not affect the standard Drupal cache.') . '</p>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Redis Page Cache'),
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
    $prefix = \Drupal::config('rpcache.settings')->get('prefix');
    //todo: not via redis-cli..
    $command = 'redis-cli --scan --pattern "' . $prefix . '*" | xargs -L 100 redis-cli DEL';
    shell_exec($command);
    drupal_set_message($this->t('All Redis Page Cache was deleted.'));
  }

}
