<?php

namespace Drupal\rpcache\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\redis\ClientFactory;

/**
 * Class HtmlResponseRPCacheSubscriber.
 */
class HtmlResponseRPCacheSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::TERMINATE][] = array('onTerminate');
    return $events;
  }

  /**
   * This method is called whenever the kernel.terminate event is
   * dispatched.
   *
   * @param PostResponseEvent $event
   */
  public function onTerminate(PostResponseEvent $event) {
    global $base_url;
    $response = $event->getResponse();

    if (!($response instanceof CacheableResponseInterface)) {
      return;
    }

    if ($response->getStatusCode() !== 200) {
      return;
    }

    $current_uri = $event->getRequest()->getRequestUri();

    // Check if there are blacklisted patterns in the URL.
    $blacklist_paths = \Drupal::config('rpcache.settings')->get('blacklist');
    if (is_array($blacklist_paths)) {
      foreach ($blacklist_paths as $path) {
        if (strpos($current_uri, $path) !== FALSE) {
          return;
        }
      }
    }

    if ($response->headers->hasCacheControlDirective('public')) {
      if (ClientFactory::hasClient()) {
        $content_to_cache = $response->getContent();
        $prefix = \Drupal::config('rpcache.settings')->get('prefix');
        $key = $prefix . $base_url . $current_uri;
        $this->redis = ClientFactory::getClient();
        $this->redis->set($key, $content_to_cache);
      }
      else {
        throw new \Exception("Redis client is not found. Is Redis module enabled and configured?");
      }
    }
  }

}
