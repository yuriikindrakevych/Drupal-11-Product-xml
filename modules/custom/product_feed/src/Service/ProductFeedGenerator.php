<?php

declare(strict_types=1);

namespace Drupal\product_feed\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the XML payload for /product.xml.
 */
class ProductFeedGenerator {

  /**
   * ProductFeedGenerator constructor.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Builds the XML string for the provided language and domain.
   */
  public function buildFeed(string $langcode, Request $request): string {
    $products = $this->loadProducts($langcode);
    $scheme = $request->getScheme();
    $host = $request->getHost();

    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = TRUE;
    $root = $dom->createElement('products');
    $root->setAttribute('lang', $langcode);
    $root->setAttribute('domain', $host);
    $dom->appendChild($root);

    foreach ($products as $product) {
      $productElement = $dom->createElement('product');
      $productElement->appendChild($dom->createElement('title', htmlspecialchars($product['title'])));
      $productElement->appendChild($dom->createElement('sku', htmlspecialchars($product['sku'])));
      $productElement->appendChild($dom->createElement('price', (string) $product['price']));
      $productElement->appendChild($dom->createElement('url', $this->buildAbsoluteUrl($product['path'], $scheme, $host)));
      $root->appendChild($productElement);
    }

    return $dom->saveXML();
  }

  /**
   * Loads product data for the language.
   */
  protected function loadProducts(string $langcode): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->condition('type', 'product')
      ->condition('status', 1)
      ->sort('changed', 'DESC');

    $nids = $query->execute();
    if (!$nids) {
      return [];
    }

    $nodes = $storage->loadMultiple($nids);
    $products = [];

    $defaultLanguage = $this->languageManager->getDefaultLanguage();

    foreach ($nodes as $node) {
      if ($node->hasTranslation($langcode)) {
        $node = $node->getTranslation($langcode);
      }
      elseif ($node->hasTranslation($defaultLanguage->getId())) {
        $node = $node->getTranslation($defaultLanguage->getId());
      }

      $products[] = [
        'title' => $node->label(),
        'sku' => $node->get('field_sku')->value ?? '',
        'price' => $node->get('field_price')->value ?? '',
        'path' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
      ];
    }

    return $products;
  }

  /**
   * Builds an absolute URL for the provided path anchored to the domain.
   */
  protected function buildAbsoluteUrl(string $absoluteUrl, string $scheme, string $host): string {
    $url = parse_url($absoluteUrl);
    $path = $url['path'] ?? '/';
    $query = isset($url['query']) ? '?' . $url['query'] : '';

    return sprintf('%s://%s%s%s', $scheme, $host, $path, $query);
  }

}
