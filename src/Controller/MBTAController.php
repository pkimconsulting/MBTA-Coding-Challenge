<?php
namespace Drupal\mbta_route\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * MBTA controller.
 */
class MBTAController extends ControllerBase {

  protected $http_client;

  private $route_url = 'https://api-v3.mbta.com/routes';
  private $schedule_url = 'https://api-v3.mbta.com/schedules?include=stop&filter[route]=';
  private $stop_url = 'https://api-v3.mbta.com/stops?filter[route]=';

  /**
   * Constructs a MTBAController object
   *
   * @param \GuzzleHttp\Client $http_client
   *   The module handler service.
   */
  public function __construct(Client $http_client_factory) {
    $this->http_client = $http_client_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('custom_http_client.client')
    );
  }

  /**
   * Returns a render array of tables for all routes.
   */
  public function showRoutes() {
    for ($i = 0; $i < 5; $i++) {
      $cid = 'mbta_routes:type-' . $i;

      if ($cache = \Drupal::cache()->get($cid)) {
          $routes = $cache->data;
      }
      else {
        $request = $this->http_client->get($this->route_url . "?filter[type]=$i");
        $routes = json_decode($request->getBody());
        \Drupal::cache()->set($cid, $routes, 60);
      }

      $build["table-$i"] = array(
        '#type' => 'table',
        '#header' => array(t("Type $i"), t('Long Name'), t('Short Name'), t('Description')),
        '#empty' => t('There are no routes for this type'),
      );

      foreach($routes->data as $n => $route) {
        $build["table-$i"][$route->id]['#attributes']['style'] = 'background:#' . $route->attributes->color . ';color:#' . $route->attributes->text_color;
        $build["table-$i"][$route->id]['color']['#plain_text'] = '';

        $url = Url::fromUri('internal:/routes/' . $route->id);
        $link = Link::fromTextAndUrl($route->attributes->long_name, $url);
        $link = $link->toRenderable();
        $build["table-$i"][$route->id]['long_name']['#markup'] = render($link);
        $build["table-$i"][$route->id]['short_name']['#plain_text'] = $route->attributes->short_name;
        $build["table-$i"][$route->id]['description']['#plain_text'] = $route->attributes->description;
      }
    }
    return $build;
  }

  /**
   * Returns a render array of tables for specific schedule.
   */
  public function showRoute($route) {
    // Cache for schedules for this route
    $cid = 'mbta_routes:schedule-' . $route;

    if ($cache = \Drupal::cache()->get($cid)) {
        $schedules = $cache->data;
    }
    else {
      $request = $this->http_client->get($this->schedule_url . $route);
      $schedules = json_decode($request->getBody());
      \Drupal::cache()->set($cid, $schedules, 60);
    }

    // Cache for stops for this route
    $cid = 'mbta_routes:stop-' . $route;
    if ($cache = \Drupal::cache()->get($cid)) {
        $stops = $cache->data;
    }
    else {
      $request = $this->http_client->get($this->stop_url . $route);
      $stops = json_decode($request->getBody());
      \Drupal::cache()->set($cid, $stop, 3600);
    }

    // Construct table for each stop
    foreach($stops->data as $n => $stop) {
      $build["table-$route-" . $stop->id] = array(
        '#type' => 'table',
        '#header' => array(t('Stop'), t('Arrival Time'), t('Departure Time')),
        '#empty' => t('There are no schedules for this route'),
      );

      // Construct stop map for lookup later
      $stop_map[$stop->id] = $stop->attributes->name; 
    }

    // Populate each stop table with schedule
    foreach($schedules->data as $n => $schedule) {
      $build["table-$route-" . $schedule->relationships->stop->data->id][$schedule->id]['stop']['#plain_text'] = $stop_map[$schedule->relationships->stop->data->id];
      $build["table-$route-" . $schedule->relationships->stop->data->id][$schedule->id]['arrival_time']['#plain_text'] = $schedule->attributes->arrival_time;
      $build["table-$route-" . $schedule->relationships->stop->data->id][$schedule->id]['departure_time']['#plain_text'] = $schedule->attributes->departure_time;
    }
    return $build;
  }

  /**
   * Returns route title
   */
  public function getRouteTitle($route) {
    $cid = 'mbta_routes:route-title-' . $route;

    if ($cache = \Drupal::cache()->get($cid)) {
        $route = $cache->data;
    }
    else {
      $request = $this->http_client->get($this->route_url . '/' . $route);
      $route = json_decode($request->getBody());
      \Drupal::cache()->set($cid, $schedules, 3600);
    }

    return !empty($route->data->attributes->long_name) ? $route->data->attributes->long_name : 'Unknown Route';
  }
}

