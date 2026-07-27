<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Content/Fields/HtmlSanitizer.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Content\Fields;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	/** Conservative allow-list sanitizer for administrator-authored rich text. */
	class HtmlSanitizer
	{
		protected static $tags = array(
			'p', 'br', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'strong', 'b', 'em', 'i', 'u', 's', 'del', 'blockquote', 'ul', 'ol', 'li',
			'a', 'img', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tfoot',
			'tr', 'th', 'td', 'caption', 'colgroup', 'col', 'pre', 'code', 'hr', 'sup', 'sub'
		);

		protected static $dropWithContent = array('script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math', 'template');

		protected static $attributes = array(
			'*' => array('class', 'title', 'style'),
			'a' => array('href', 'target', 'rel'),
			'img' => array('src', 'alt', 'width', 'height', 'loading'),
			'ol' => array('start', 'type'),
			'li' => array('value'),
			'table' => array('width'),
			'th' => array('colspan', 'rowspan', 'scope'),
			'td' => array('colspan', 'rowspan'),
			'col' => array('span', 'width'),
		);

		protected static $styles = array(
			'text-align', 'color', 'background-color', 'font-size', 'font-family',
			'font-weight', 'font-style', 'text-decoration', 'line-height',
			'margin-left', 'margin-right', 'padding-left', 'padding-right',
			'width', 'height', 'max-width', 'vertical-align', 'white-space'
		);

		public static function clean($html)
		{
			$html = (string) $html;
			if ($html === '' || trim($html) === '') { return $html; }
			if (!class_exists('DOMDocument')) { return self::fallback($html); }

			$previous = libxml_use_internal_errors(true);
			$document = new \DOMDocument('1.0', 'UTF-8');
			$wrapped = '<!DOCTYPE html><html><body><div data-ave-root="1">' . $html . '</div></body></html>';
			$loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			if (!$loaded) {
				libxml_clear_errors(); libxml_use_internal_errors($previous);
				return self::fallback($html);
			}

			$root = null;
			foreach ($document->getElementsByTagName('div') as $node) {
				if ($node->getAttribute('data-ave-root') === '1') { $root = $node; break; }
			}

			if (!$root) {
				libxml_clear_errors(); libxml_use_internal_errors($previous);
				return self::fallback($html);
			}

			self::sanitizeChildren($root);
			$result = '';
			foreach ($root->childNodes as $child) { $result .= $document->saveHTML($child); }
			libxml_clear_errors(); libxml_use_internal_errors($previous);
			return $result;
		}

		protected static function sanitizeChildren(\DOMNode $parent)
		{
			$children = array();
			foreach ($parent->childNodes as $child) { $children[] = $child; }
			foreach ($children as $child) {
				if ($child->nodeType !== XML_ELEMENT_NODE) { continue; }
				$tag = strtolower($child->nodeName);
				if (in_array($tag, self::$dropWithContent, true)) { $parent->removeChild($child); continue; }
				if (!in_array($tag, self::$tags, true)) {
					self::sanitizeChildren($child);
					while ($child->firstChild) { $parent->insertBefore($child->firstChild, $child); }
					$parent->removeChild($child);
					continue;
				}

				self::sanitizeAttributes($child, $tag);
				self::sanitizeChildren($child);
			}
		}

		protected static function sanitizeAttributes(\DOMElement $node, $tag)
		{
			$allowed = self::$attributes['*'];
			if (isset(self::$attributes[$tag])) { $allowed = array_merge($allowed, self::$attributes[$tag]); }
			$remove = array();
			foreach ($node->attributes as $attribute) {
				$name = strtolower($attribute->name);
				if (!in_array($name, $allowed, true) && strpos($name, 'data-') !== 0 && strpos($name, 'aria-') !== 0) { $remove[] = $attribute->name; continue; }
				$value = trim((string) $attribute->value);
				if (($name === 'href' || $name === 'src') && !self::safeUrl($value)) {
					$remove[] = $attribute->name;
				} elseif ($name === 'style') {
					$style = self::safeStyle($value);
					if ($style === '') { $remove[] = $attribute->name; } else { $node->setAttribute('style', $style); }
				}
			}

			foreach ($remove as $name) { $node->removeAttribute($name); }
			if ($tag === 'a' && strtolower($node->getAttribute('target')) === '_blank') {
				$rel = preg_split('/\s+/', strtolower(trim($node->getAttribute('rel')))) ?: array();
				$rel = array_values(array_unique(array_filter(array_merge($rel, array('noopener', 'noreferrer')))));
				$node->setAttribute('rel', implode(' ', $rel));
			}

			if ($tag === 'img' && !$node->hasAttribute('loading')) { $node->setAttribute('loading', 'lazy'); }
		}

		protected static function safeUrl($url)
		{
			$url = html_entity_decode(trim((string) $url), ENT_QUOTES, 'UTF-8');
			if ($url === '' || $url[0] === '/' || $url[0] === '#' || strpos($url, './') === 0 || strpos($url, '../') === 0) { return true; }
			$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
			return in_array($scheme, array('http', 'https', 'mailto', 'tel'), true);
		}

		protected static function safeStyle($style)
		{
			if (preg_match('/(?:expression|url\s*\(|javascript|vbscript|behavior\s*:|@import|-moz-binding)/i', (string) $style)) { return ''; }
			$out = array();
			foreach (explode(';', (string) $style) as $rule) {
				$parts = explode(':', $rule, 2);
				if (count($parts) !== 2) { continue; }
				$property = strtolower(trim($parts[0])); $value = trim($parts[1]);
				if (in_array($property, self::$styles, true) && $value !== '' && !preg_match('/[<>]/', $value)) { $out[] = $property . ': ' . $value; }
			}

			return implode('; ', $out);
		}

		protected static function fallback($html)
		{
			$html = preg_replace('#<(script|style|iframe|object|embed|form|svg|math|template)\b[^>]*>.*?</\1>#is', '', (string) $html);
			$html = strip_tags($html, '<p><br><div><span><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><s><del><blockquote><ul><ol><li><a><img><figure><figcaption><table><thead><tbody><tfoot><tr><th><td><caption><colgroup><col><pre><code><hr><sup><sub>');
			$html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
			return preg_replace('/\s+(href|src)\s*=\s*(["\'])\s*(?:javascript|vbscript|data):.*?\2/i', '', $html);
		}
	}
