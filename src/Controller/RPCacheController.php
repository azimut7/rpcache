<?php

namespace Drupal\rpcache\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\redis\ClientFactory;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class RPCacheController.
 */
class RPCacheController extends ControllerBase {

  /**
   * Endpoint for URL purging
   *
   * POST request to the url:
   *  /rpcache/rpcache-clear
   *
   * With headers:
   *  X-RPCache-Type = 'url'
   *  X-RPCache-Url = url for purge
   *
   * @return string
   */
  public function clear() {
    if ('POST' == $request_type = \Drupal::request()->getMethod()) {

      if (false === $has_type = \Drupal::request()->headers->has('X-RPCache-Type')) {
        $response  = 'There is no X-RPCache-Type header';
      }
      elseif ('url' !== $type = \Drupal::request()->headers->get('X-RPCache-Type')) {
        $response  = 'Type error in X-RPCache-Type header';
      }
      elseif (false === $has_url = \Drupal::request()->headers->has('X-RPCache-Url')) {
        $response  = 'There is no X-RPCache-Url header';
      }
      else {
        if (ClientFactory::hasClient()) {
          $prefix = \Drupal::config('rpcache.settings')->get('prefix');;
          $url = \Drupal::request()->headers->get('X-RPCache-Url');
          $key = $prefix . $url;
          $response = 'Ok';
          //$response = 'OK, sended to Redis, key: ' . '*' . $key . '*';
          $this->redis = ClientFactory::getClient();
          $this->redis->del($key);
        }
        else {
          throw new \Exception("Redis client is not found. Is Redis module enabled and configured?");
        }
      }

    }
    else {
      $response  = 'Incorrect request type.';
    }

    return new JsonResponse($response);
  }

}
