<?php

namespace Xzito\Portfolios;

class BulkAction {
  public const ACTION_ID = 'tag_by_portfolio';
  public const ACTION_NAME = 'Tag by portfolio';
  public const ATTACHMENT_FIELD_KEY = 'attachment_portfolio_photos';
  public const COLUMN_ID = 'portfolios';
  public const COLUMN_LABEL = 'Portfolios';
  public const QUERY_ARG = 'tagged-by-portfolio';

  private $action_ids;

  private static function tagged_query_arg() {
    if (isset($_REQUEST[self::QUERY_ARG])) {
      return $_REQUEST[self::QUERY_ARG];
    } else {
      return null;
    }
  }

  private static function tagging_by_portfolio($action) {
    return preg_match('/Tag\sby\sportfolio/', $action);
  }

  private static function prioritize_column($columns) {
    $column_ids = array_keys($columns);

    $portfolios_column_index = array_search(self::COLUMN_ID, $column_ids);
    $title_column_index = array_search('title', $column_ids);

    $from_index = $portfolios_column_index;
    $to_index = $title_column_index + 1;

    $columns = Helpers::move_array_element($columns, $from_index, $to_index);

    return $columns;
  }

  private static function portfolio_already_tagged($portfolio, $terms) {
    return in_array($portfolio->term()->term_id, $terms);
  }

  private static function current_terms_for($attachment_id) {
    return (array) get_field(self::ATTACHMENT_FIELD_KEY, $attachment_id);
  }

  private static function update_terms($attachment_id, $terms) {
    $flag = update_field(self::ATTACHMENT_FIELD_KEY, $terms, $attachment_id);
    $updated_terms = get_field(self::ATTACHMENT_FIELD_KEY, $attachment_id);

    if (self::no_terms_updated($terms, $updated_terms)) {
      $flag = true;
    }

    return $flag;
  }

  private static function no_terms_updated($existing_terms, $updated_terms) {
    return array_diff($existing_terms, $updated_terms) == [];
  }

  public function __construct() {
    add_action('admin_notices', [$this, 'show_notice']);
    add_action('manage_media_custom_column', [$this, 'populate_column']);

    add_filter('bulk_actions-upload', [$this, 'register_action']);
    add_filter('handle_bulk_actions-upload', [$this, 'handle_action'], 10, 3);
    add_filter('manage_media_columns', [$this, 'add_column']);
  }

  public function show_notice() {
    $status = self::tagged_query_arg();

    if (isset($status)) {
      if ($status === 'success') {
        $message = "<div class=\"notice notice-success is-dismissible\">";
        $message .= "<p>Successfully tagged selected items by portfolio.</p>";
        $message .= "</div>";

        print($message);
      } elseif ($status === 'failed') {
        $message = "<div class=\"notice notice-error is-dismissible\">";
        $message .= "<p>One or more selected items couldn't be tagged by ";
        $message .= "portfolio.</p>";
        $message .= "</div>";

        print($message);
      }
    }
  }

  public function register_action($bulk_actions) {
    $this->set_action_ids();

    foreach ($this->action_ids as $id) {
      $bulk_actions[$id] = $id;
    }

    return $bulk_actions;
  }

  public function handle_action($redirect_to, $doaction, $post_ids) {
    $redirect_to = $this->unset_query_args($redirect_to);

    if (self::tagging_by_portfolio($doaction)) {
      $status = false;

      $portfolio = $this->portfolio_from_doaction($doaction);
      $status = $this->run_bulk_action($portfolio->id(), $post_ids);

      $redirect_to = $this->add_query_args($status, $redirect_to);
    }

    return $redirect_to;
  }

  public function add_column($columns) {
    $columns[self::COLUMN_ID] = self::COLUMN_LABEL;

    $reordered_columns = self::prioritize_column($columns);

    return $reordered_columns;
  }

  public function populate_column($column) {
    global $post;

    $terms = wp_get_object_terms($post->ID, Portfolios::TAXONOMY_ID) ?? [];
    $term_names = [];

    array_map(function ($term) use (&$term_names) {
      $term_names[] = $term->name;
    }, $terms);

    $terms_string = implode(', ', $term_names);

    if ($column === self::COLUMN_ID) {
      print($terms_string);
    }
  }

  private function set_action_ids() {
    $action_ids = [];

    array_map(function ($portfolio) use (&$action_ids) {
      $id = $this->action_id_for($portfolio);
      $name = $this->action_name_for($portfolio);

      $action_ids[$id] = $name;
    }, Portfolios::all());

    sort($action_ids);

    $this->action_ids = $action_ids;
  }

  private function action_id_for($portfolio) {
    $base = self::ACTION_ID;
    $id = $portfolio->id();

    return "$base-$id";
  }

  private function action_name_for($portfolio) {
    $base = self::ACTION_NAME;
    $name = $portfolio->name();

    return "$base: $name";
  }

  private function unset_query_args($url) {
    if (strpos($url, self::QUERY_ARG)) {
      $url = remove_query_arg(self::QUERY_ARG, $url);
    }

    return $url;
  }

  private function add_query_args($status, $url) {
    if ($status) {
      $url = add_query_arg(self::QUERY_ARG, 'success', $url);
    } else {
      $url = add_query_arg(self::QUERY_ARG, 'failed', $url);
    }

    return $url;
  }

  private function run_bulk_action($portfolio_id, $attachment_ids) {
    $status = false;
    $portfolio = new Portfolio($portfolio_id);

    foreach ($attachment_ids as $attachment_id) {
      $terms = self::current_terms_for($attachment_id) ?? [];

      if (!self::portfolio_already_tagged($portfolio, $terms)) {
        $terms[] = $portfolio->term()->term_id;
      }

      $status = self::update_terms($attachment_id, $terms);
    }

    return $status;
  }

  private function portfolio_from_doaction($doaction) {
    list($_, $name) = explode(': ', $doaction);

    return $this->portfolio_from_name($name);
  }

  private function portfolio_from_name($name) {
    return Portfolio::find_by_name($name);
  }
}
