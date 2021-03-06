<?php

namespace Lib\Setup;

/**
 * Theme 基本セットアップ
 */
add_action('after_setup_theme', function () {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('align-wide');
  add_theme_support('html5', [
    'caption',
    'comment-form',
    'comment-list',
    'gallery',
    'search-form',
  ]);
  // add_theme_support('wp-block-styles');
  // add_theme_support('editor-styles');

  // Register wp_nav_menu() menus
  // http://codex.wordpress.org/Function_Reference/register_nav_menus
  register_nav_menus([
    'primary_navigation' => __('Primary Navigation', THEME_DOMAIN),
  ]);
  register_nav_menus([
    'footer_navigation_bottom' => __('Footer Navigation Bottom', THEME_DOMAIN),
  ]);
});

/**
 * asset enqueue
 */
add_action(
  'wp_enqueue_scripts',
  function () {
    if (!is_single() || !is_singular()) {
      wp_dequeue_style('wp-block-library');
    }

    if (!IS_ENABLE_GOOGLE_FONTS || count(GOOGLE_FONTS) <= 0) {
      return;
    }

    // web fonts
    if (count(GOOGLE_FONTS) > 0) {
      foreach (GOOGLE_FONTS as $queue => $src) {
        wp_enqueue_style($queue, $src, false, null);
      }
    }
  },
  100
);

/**
 * テーマアセット用キャッシュバスター
 *
 * @param string $path テーマ内のパス
 * @return string ハッシュ ID クエリ付きURL
 */
function cache_buster($path = '')
{
  if (!$path) {
    return '';
  }

  $asset_path = get_template_directory() . $path;
  $asset_uri = get_template_directory_uri() . $path;
  $hash_id = file_exists($asset_path)
    ? '?id=' . hash_file('fnv132', $asset_path)
    : '';

  return $asset_uri . $hash_id;
}

/**
 * remove wp_head meta
 */
add_action(
  'wp_enqueue_scripts',
  function () {
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'index_rel_link');
    remove_action('wp_head', 'parent_post_rel_link', 10, 0);
    remove_action('wp_head', 'start_post_rel_link', 10, 0);
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
    remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
    remove_action('wp_head', 'feed_links', 2);
    remove_action('wp_head', 'feed_links_extra', 3);

    // 絵文字関連削除
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');

    // Embed関連削除
    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
    remove_action('template_redirect', 'rest_output_link_header', 11);
  },
  102
);

/**
 * REST API から "user" endpoint を削除
 */
add_filter(
  'rest_endpoints',
  function ($endpoints) {
    if (isset($endpoints['/wp/v2/users'])) {
      unset($endpoints['/wp/v2/users']);
    }
    if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
      unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpoints;
  },
  10,
  1
);

/**
 * remove recent_comments_style
 */
add_action('widgets_init', function () {
  global $wp_widget_factory;
  remove_action('wp_head', [
    $wp_widget_factory->widgets['WP_Widget_Recent_Comments'],
    'recent_comments_style',
  ]);
});

/**
 * remove comments
 */
add_filter('comments_open', '__return_false');

/**
 * アセットキューから preload を生成
 */
add_action(
  'wp_head',
  function () {
    global $wp_version;
    global $wp_styles;
    global $wp_scripts;

    $is_wpo_html_minify = false;
    $wpo_option = get_option('wpo_minify_config');

    if (!empty($wpo_option)) {
      $is_wpo_html_minify =
        $wpo_option['enabled'] && $wpo_option['html_minification'];
    }

    // preload as style
    foreach ($wp_styles->queue as $handle) {
      $src = $wp_styles->registered[$handle]->src;
      $ver = $wp_styles->registered[$handle]->ver;

      if (empty($src)) {
        continue;
      }

      if (strpos($src, '/wp-includes') === 0) {
        $src = $wp_styles->base_url . $src;

        if (!$is_wpo_html_minify) {
          $src .= '?ver=' . $wp_version;
        }
      } elseif (!empty($ver)) {
        $src .= '?ver=' . $ver;
      }

      if (0 === strpos($src, '?')) {
        continue;
      }

      echo '<link rel="preload" as="style" href="' .
        esc_url($src) .
        '">' .
        "\n";
    }

    // preload as script
    foreach ($wp_scripts->queue as $handle) {
      if ($wp_scripts->registered[$handle]->handle === 'polyfill') {
        continue;
      }

      $src = $wp_scripts->registered[$handle]->src;
      $ver = $wp_scripts->registered[$handle]->ver;

      if (strpos($src, '/wp-includes') === 0) {
        $src = $wp_scripts->base_url . $src . '?ver=' . $wp_version;
      } elseif (!empty($ver)) {
        $src .= '?ver=' . $ver;
      }

      if (strpos($handle, 'nomodule/') !== false) {
        continue;
      }

      if (strpos($handle, 'modules/') !== false) {
        echo '<link rel="modulepreload" href="' . esc_url($src) . '">' . "\n";
        continue;
      }

      echo '<link rel="preload" as="script" href="' .
        esc_url($src) .
        '">' .
        "\n";
    }
  },
  2,
  1
);

/**
 * resource hints 追加
 */
add_filter(
  'wp_resource_hints',
  function ($hints, $relation_type) {
    if (is_admin() || is_user_logged_in()) {
      return $hints;
    }
    if ('dns-prefetch' === $relation_type) {
      $hints = array_filter($hints, function ($val) {
        return $val !== 'fonts.googleapis.com' ? true : false;
      });

      if (IS_ENABLE_GOOGLE_TAG_MANAGER) {
        $hints[] = 'https://www.googletagmanager.com';
      }
    }
    if ('preconnect' === $relation_type) {
      if (IS_ENABLE_GOOGLE_FONTS) {
        $hints[] = 'https://fonts.googleapis.com';
        $hints[] = 'https://fonts.gstatic.com';
      }
    }
    return $hints;
  },
  10,
  2
);

/**
 * script loader tag に module　属性を追加
 */
add_filter(
  'script_loader_tag',
  function ($tag /* , $handle */) {
    if (is_admin()) {
      return $tag;
    }

    $replace = $tag;

    if (
      preg_match('/module\//', $replace) &&
      preg_match('/text\/javascript/', $replace)
    ) {
      return str_replace('text/javascript', 'module', $replace);
    }

    if (preg_match('/nomodule\//', $replace)) {
      // $replace = str_replace('<script ', '<script nomodule ', $replace);
      $replace = str_replace("type='text/javascript'", 'nomodule', $replace);
    }

    return $replace;
  },
  10,
  2
);
