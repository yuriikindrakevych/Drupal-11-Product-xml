<?php

declare(strict_types=1);

namespace Drupal\product_feed\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\product_feed\Service\ProductFeedGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller that exposes the /product.xml feed.
 */
class ProductFeedController extends ControllerBase {

  /**
   * The feed generator service.
   */
  protected readonly ProductFeedGenerator $generator;

  /**
   * Constructs the controller.
   */
  public function __construct(
    ProductFeedGenerator $generator,
    LanguageManagerInterface $languageManager,
  ) {
    $this->generator = $generator;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('product_feed.generator'),
      $container->get('language_manager'),
    );
  }

  /**
   * Returns the XML feed for the current domain.
   */
  public function feed(Request $request): Response {
    $language = $this->languageManager->getCurrentLanguage();
    $feed = $this->generator->buildFeed($language->getId(), $request);

    $response = new Response($feed);
    $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
    $response->setMaxAge(900);
    $response->headers->addCacheControlDirective('must-revalidate', TRUE);

    return $response;
  }

}
