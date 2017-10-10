<?php

namespace Drupal\rpcache\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures forms module settings.
 */
class RPCacheConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rpcache_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'rpcache.settings',
    ];
  }
  //$prefix = \Drupal::config('rpcache.settings')->get('prefix');
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $config = $this->config('rpcache.settings');

    $form['cache'] = [
      '#type' => 'details',
      '#title' => $this->t('Cache Clear'),
      '#open' => TRUE,
    ];

    $form['cache']['clear_on_drupal_cache_rebuild'] = [
      '#title' => $this->t('Clear Redis Page Cache by Drupal «Clear all caches»'),
      '#type' => 'checkbox',
      '#description' => $this->t("If checked this all Redis Page Cache entries in the Redis will be deleted by Drupal «Clear all caches» or by «drush cr» command"),
      '#default_value' => $config->get('clear_on_drupal_cache_rebuild'),
    ];

    $form['list'] = [
      '#type' => 'details',
      '#title' => $this->t('Do not cache these pages'),
      '#open' => FALSE,
    ];

    // Retrieve the existing blacklist and initiatlize the counter.
    $blacklist = $config->get('blacklist');
    //kint($blacklist);
    if (is_null($form_state->get('blacklist_items_count'))) {
      if (empty($blacklist)) {
        $form_state->set('blacklist_items_count', 1);
      }
      else {
        $form_state->set('blacklist_items_count', count($blacklist));
      }
    }
    // Define the fields based on whats stored in form state.
    $info = '<p>' .
      $this->t('URLs that contains these paths will not cache in the Redis Page Cache') . '<br />' .
      $this->t('For the correct purging you need to have the same blacklists here and in the 
            <a href="@purge_settings" target="_blank">URLs queuer settings</a> (QUEUE — URLs queuer — Configure).  
            <a href="@url_purge" target="_blank">URLs queuer module on Drupal.org</a>',
            ['@url_purge' => 'https://www.drupal.org/project/purge_queuer_url',
              '@purge_settings' => '/admin/config/development/performance/purge']) .
            '</p>';
    $max = $form_state->get('blacklist_items_count');
    $form['list']['blacklist']['blacklist'] = [
      '#tree' => TRUE,
      '#prefix' => $info . '<div id="blacklist-wrapper">',
      '#suffix' => '</div>',
    ];
    for ($delta = 0; $delta < $max; $delta++) {
      if (!isset($form['blacklist']['blacklist'][$delta])) {
        $element = [
          '#type' => 'textfield',
          '#default_value' => isset($blacklist[$delta]) ? $blacklist[$delta] : '',
        ];
        $form['list']['blacklist']['blacklist'][$delta] = $element;
      }
    }
    // Define the add button.
    $form['list']['blacklist']['add'] = [
      '#type' => 'submit',
      '#name' => 'add',
      '#value' => $this->t('Add'),
      '#submit' => [[$this, 'addMoreSubmit']],
      '#ajax' => [
        'callback' => [$this, 'addMoreCallback'],
        'wrapper' => 'blacklist-wrapper',
        'effect' => 'fade',
      ],
    ];

    $form['prefix'] = [
      '#type' => 'details',
      '#title' => $this->t('prefix'),
      '#open' => false,
    ];

    $form['prefix']['redis_prefix'] = [
      '#title' => $this->t('Prefix for keys'),
      '#type' => 'textfield',
      '#size' => 30,
      '#description' => $this->t("All entries in the Redis will have keys: prefix + full url, for example: «rpcache:http://drupalvm.dev/». 
      You need to setup the same prefix in the nginx configuration see below."),
      '#default_value' => $config->get('prefix'),
    ];

    $form['nginx'] = [
      '#type' => 'details',
      '#title' => $this->t('Nginx config example'),
      '#open' => FALSE,
    ];

    $nginx_example = '
      <pre>
        ...
        
        location / {
          try_files $uri @rpcache;
        }
        
        # Redis Page Cache url for purging, allow access only from localhost
        location /rpcache/rpcache-clear {
            allow 127.0.0.1;
            deny all;
            try_files $uri /index.php?$query_string;
        }
        
        location @rpcache {
            
            ## Disable logs (there are tons of info, warn etc when not found in cache)
            error_log off;
            
            ## Header to see that page was handled by RedisPageCache 
            add_header X-RPCache \'HIT\';
        
            error_page 418 = @rewrite;
        
            if ($http_cookie ~* "SESS") {
               return 418;
            }
            if ($request_method !~ ^(GET|HEAD)$ ) {
               return 418;
            }
            default_type text/html;
        
            set $redis_key "' . $config->get('prefix') . '$scheme://$host$request_uri";
        
            ## 127.0.0.1 not localhost!
            redis_pass 127.0.0.1:6379;;
        
            proxy_intercept_errors on;
            error_page 404 502 = @rewrite;
        }
        
        ...
      </pre>
    ';

    $form['nginx']['nginx_example'] = [
      '#markup' => '<p>' .
        $this->t('You need to have nginx with <a href="@redis" target="_blank">HTTP Redis module</a>',
                ['@redis' => 'https://www.nginx.com/resources/wiki/modules/redis/']) . '<br />' .
        $this->t('In this example Redis work on localhost:6379.') . '<br />' .
        $this->t('Be carefull with trailing slashes, <i>http://drupalvm.dev/mypage</i> 
                and <i>http://drupalvm.dev/mypage/</i> these are different pages.') . '<br />' .
        $this->t('You can check for «removing trainling slashes» on the 
                 <a href="@redirect_module" target="_blank">Redirect module settings page</a> (if enabled)',
                 ['@redirect_module' => '/admin/config/search/redirect/settings']) .
                '</p>' . $nginx_example,
      '#open' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Let the form rebuild the blacklist textfields.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function addMoreSubmit(array &$form, FormStateInterface $form_state) {
    $count = $form_state->get('blacklist_items_count');
    $count++;
    $form_state->set('blacklist_items_count', $count);
    $form_state->setRebuild();
  }

  /**
   * Adds more textfields to the blacklist fieldset.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function addMoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['list']['blacklist']['blacklist'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Remove empty values from the blacklist so this doesn't cause issues.
    $blacklist = [];
    foreach ($form_state->getValue('blacklist') as $string) {
      if (!empty(trim($string))) {
        $blacklist[] = $string;
      }
    }
    $form_state->setValue('blacklist', $blacklist);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //$values = $form_state->getValues();
    $this->config('rpcache.settings')
      ->set('prefix', $form_state->getValue('redis_prefix'))
      ->set('clear_on_drupal_cache_rebuild', $form_state->getValue('clear_on_drupal_cache_rebuild'))
      ->set('blacklist', $form_state->getValue('blacklist'))
      ->save();
  }

}