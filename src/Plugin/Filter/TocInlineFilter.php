<?php

declare(strict_types=1);

namespace Drupal\toc_inline\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Replaces [toc] with a generated table of contents.
 *
 * @Filter(
 *   id = "toc_inline_filter",
 *   title = @Translation("Inline table of contents"),
 *   description = @Translation("Replaces [toc] with a generated table of contents based on headings."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE
 * )
 */
final class TocInlineFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    if (!preg_match('/\[toc\]/i', $text)) {
      return new FilterProcessResult($text);
    }

    libxml_use_internal_errors(TRUE);

    $dom = new \DOMDocument('1.0', 'UTF-8');
    $wrapped = '<?xml encoding="UTF-8"><!DOCTYPE html><html><body>' . $text . '</body></html>';
    $loaded = $dom->loadHTML($wrapped, LIBXML_NOWARNING | LIBXML_NOERROR);

    if (!$loaded) {
      return new FilterProcessResult($text);
    }

    $xpath = new \DOMXPath($dom);
    $headings = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
    if (!$headings instanceof \DOMNodeList) {
      return new FilterProcessResult($text);
    }

    $used_ids = [];
    $toc_items = [];

    foreach ($headings as $heading) {
      if (!$heading instanceof \DOMElement) {
        continue;
      }

      $title = trim($heading->textContent ?? '');
      if ($title === '') {
        continue;
      }

      $level = (int) substr($heading->tagName, 1);
      $id = trim($heading->getAttribute('id'));

      if ($id === '') {
        $id = $this->generateUniqueId($title, $used_ids);
        $heading->setAttribute('id', $id);
      }
      else {
        $id = $this->deduplicateExistingId($id, $heading, $used_ids);
      }

      $toc_items[] = [
        'id' => $id,
        'title' => $title,
        'level' => $level,
      ];
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body instanceof \DOMElement) {
      return new FilterProcessResult($text);
    }

    $inner_html = $this->getInnerHtml($body);

    if ($toc_items === []) {
      $processed = preg_replace('/\[toc\]/i', '', $inner_html, 1) ?? $inner_html;
      return new FilterProcessResult($processed);
    }

    $toc_markup = $this->buildTocMarkup($toc_items);
    $processed = preg_replace('/\[toc\]/i', $toc_markup, $inner_html, 1) ?? $inner_html;

    return new FilterProcessResult($processed);
  }

  /**
   * Builds a unique heading ID from text.
   */
  private function generateUniqueId(string $title, array &$used_ids): string {
    $base = Html::getId($title);
    if ($base === '') {
      $base = 'section';
    }

    $candidate = $base;
    $suffix = 1;

    while (isset($used_ids[$candidate])) {
      $candidate = $base . '-' . $suffix;
      $suffix++;
    }

    $used_ids[$candidate] = TRUE;
    return $candidate;
  }

  /**
   * Ensures an existing ID is unique within the current document.
   */
  private function deduplicateExistingId(string $id, \DOMElement $heading, array &$used_ids): string {
    $base = Html::getId($id);
    if ($base === '') {
      $base = 'section';
    }

    $candidate = $base;
    $suffix = 1;

    while (isset($used_ids[$candidate])) {
      $candidate = $base . '-' . $suffix;
      $suffix++;
    }

    if ($candidate !== $id) {
      $heading->setAttribute('id', $candidate);
    }

    $used_ids[$candidate] = TRUE;
    return $candidate;
  }

  /**
   * Returns inner HTML for a DOM element.
   */
  private function getInnerHtml(\DOMElement $element): string {
    $html = '';

    foreach ($element->childNodes as $child) {
      $html .= $element->ownerDocument->saveHTML($child);
    }

    return $html;
  }

  /**
   * Builds flat ToC markup.
   */
  private function buildTocMarkup(array $items): string {
    if (empty($items)) {
      return '';
    }

    $html = '<nav class="toc-inline" aria-label="Table of contents">';
    $html .= '<div class="toc-inline__title">Contents</div>';

    $html .= '<ul class="toc-inline__list">';

    $current_level = $items[0]['level'];

    foreach ($items as $index => $item) {
      $level = (int) $item['level'];
      $title = \Drupal\Component\Utility\Html::escape($item['title']);
      $id = \Drupal\Component\Utility\Html::escape($item['id']);

      // Going deeper → open nested lists
      if ($level > $current_level) {
        while ($level > $current_level) {
          $html .= '<ul>';
          $current_level++;
        }
      }

      // Going up → close lists
      elseif ($level < $current_level) {
        while ($level < $current_level) {
          $html .= '</li></ul>';
          $current_level--;
        }
        $html .= '</li>';
      }

      // Same level → close previous item
      else {
        if ($index !== 0) {
          $html .= '</li>';
        }
      }

      // Add item
      $html .= '<li class="toc-inline__item toc-inline__item--level-' . $level . '">';
      $html .= '<a class="toc-inline__link" href="#' . $id . '">' . $title . '</a>';
    }

    // Close remaining open tags
    while ($current_level > $items[0]['level']) {
      $html .= '</li></ul>';
      $current_level--;
    }

    $html .= '</li></ul>';
    $html .= '</nav>';

    return $html;
  }

}
