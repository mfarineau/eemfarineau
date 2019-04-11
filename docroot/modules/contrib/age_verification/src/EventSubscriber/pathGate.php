<?php

/**
 *
 * Contains \Drupal\age_verification\EventSubscriber\pathGate.
 */

 // Declare the namespace for our own event subscriber.
namespace Drupal\age_verification\EventSubscriber;

use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Event Subscriber PathGate.
 */
class PathGate implements EventSubscriberInterface {
  /**
     * The path matcher.
     *
     * @var \Drupal\Core\Path\PathMatcherInterface
     */
  protected $pathMatcher;

  /**
     * The current user.
     *
     * @var \Drupal\Core\Session\AccountInterface
     */
  protected $currentUser;

  /**
     * Constructs a new Redirect404Subscriber.
     *
     * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
     *   The path matcher service.
     * @param \Drupal\Core\Session\AccountInterface $current_user
     *   Current user.
     */
  public function __construct(PathMatcherInterface $path_matcher, AccountInterface $current_user) {
        $this->pathMatcher = $path_matcher;
        $this->currentUser = $current_user;
      }

  /**
     * Code that should be triggered on event specified.
     */
  public function onRespond(FilterResponseEvent $event) {
        // The RESPONSE event occurs once a response was created for replying to a request.
        // For example you could override or add extra HTTP headers in here.
        $response = $event->getResponse();

        $session = \Drupal::request()->getSession();

        $age_verified = $session->get('age_verified');

        // If we have a valid session.
        if ($age_verified == TRUE) {
            return;
    }

    // Make sure front page module is not run when using cli (drush).
    // Make sure front page module does not run when installing Drupal either.
    if (PHP_SAPI === 'cli' || drupal_installation_attempted()) {
            return;
    }

    // Don't run when site is in maintenance mode.
    if (\Drupal::state()->get('system.maintenance_mode')) {
            return;
    }
    // Ignore non index.php requests (like cron).
    if (!empty($_SERVER['SCRIPT_FILENAME']) && realpath(DRUPAL_ROOT . '/index.php') != realpath($_SERVER['SCRIPT_FILENAME'])) {
            return;
    }

    // Get saved settings and other needed objects.
    $config = \Drupal::config('age_verification.settings');

    // Now we need to explode the age_verification_user_agents field to separate
    // lines.
    $user_agents = explode("\n", $config->get('age_verification_user_agents'));
    $http_user_agent = \Drupal::request()->server->get('HTTP_USER_AGENT');

    // For each one of the lines we want to trim white space and empty lines.
    foreach ($user_agents as $key => $user_agent) {
            // If a user has string from $user_agent.
            if (empty($user_agent)) {
                unset($lines[$key]);
      }
      // To be sure we match proper string, we need to trim it.
      $user_agent = trim($user_agent);

      if ($http_user_agent == $user_agent) {
                return;
      }
    }

    // Send to proper page if logged in.
    $skip_urls_config = $config->get('age_verification_urls_to_skip');

    $skip_urls[] = '/admin';
    $skip_urls[] = '/admin/*';
    $skip_urls[] = '/age-verification';
    $skip_urls[] = '/user/login';

    // Append the urls to skips with some hardcoded urls.
    $skipPaths = $skip_urls_config . "\r\n" . implode("\r\n", $skip_urls);

    $request_path = \Drupal::service('path.current')->getPath();

    // Check if the paths don't match then redirect to the age verification form.
    $match = \Drupal::service('path.matcher')->matchPath($request_path, $skipPaths);
    $is_front = \Drupal::service('path.matcher')->isFrontPage();

    // If not the front page then append the requested path alias as a destination parameter.
    if ($is_front == FALSE) {
            $current_uri = \Drupal::request()->getRequestUri();
            $destination = '?destination=' . $current_uri;
          }
    else {
            $destination = '';
          }

    // If the requested path is not restricted.
    if ($match == TRUE) {
            return;
    }
    // Redirect to the /age-verification with the destination.
    elseif ($match == FALSE) {

          $redirect = new RedirectResponse('/age-verification' . $destination);
      $event->setResponse($redirect);
    }

  }

  /**
     * {@inheritdoc}
     */
  public static function getSubscribedEvents() {
        // For this example I am using KernelEvents constants (see below a full list).
        $events[KernelEvents::RESPONSE][] = ['onRespond'];
        return $events;
  }

}
