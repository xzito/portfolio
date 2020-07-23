<?php

namespace Xzito\Portfolio;

class Portfolio {
  public const QUERY_ARG = 'filter-by-portfolio';
  public const TAXONOMY_ID = 'related_portfolio';

  public static function all($options = ['return_type' => 'object']) {
    $return_type = self::set_return_type($options);

    $query = new \WP_Query([
      'nopaging' => true,
      'post_type' => PortfolioPostType::ID,
      'fields' => 'ids',
    ]);

    $portfolio_piece_ids = $query->posts ?? [];

    if ($return_type === 'ids') {
      $portfolio_pieces = $portfolio_piece_ids;
    } else {
      $portfolio_pieces = [];

      array_map(function ($id) use (&$portfolio_pieces) {
        $portfolio_pieces[] = new PortfolioPiece($id);
      }, $portfolio_piece_ids);
    }

    return $portfolio_pieces;
  }

  private static function set_return_type($options) {
    $return_types = ['id', 'name', 'slug', 'object'];
    $return_type = $options['return_type'];

    if (!in_array($return_type, $return_types)) {
      $return_type = 'object';
    }

    return $return_type;
  }

  private static function filter_query_arg() {
    return $_REQUEST[self::QUERY_ARG] ?? false;
  }

  private static function selected($filtered_slug, $portfolio_piece_slug) {
    if ($filtered_slug === $portfolio_piece_slug) {
      $markup = 'selected="selected"';
    }

    return $markup ?? '';
  }

  private static function in_media_library() {
    return (get_current_screen()->base === 'upload' ? true : false);
  }

  public function __construct() {
    add_action('plugins_loaded', [$this, 'create_options_page']);
    add_action('init', [$this, 'create_post_type'], 0);
    add_action('init', [$this, 'create_taxonomy'], 0);
    add_action('init', [$this, 'create_bulk_action'], 0);
    add_action('init', [$this, 'create_terms'], 10);
    add_action('restrict_manage_posts', [$this, 'create_filters']);
    add_action('acf/save_post', [$this, 'set_fields_on_save'], 20);
    add_action('wp_trash_post', [$this, 'destroy_terms']);

    add_filter('parse_query', [$this, 'filter_query']);
  }

  public function create_post_type() {
    new PortfolioPostType();
  }

  public function create_taxonomy() {
    new RelatedPortfolioTaxonomy();
  }

  public function create_bulk_action() {
    new BulkAction();
  }

  public function create_options_page() {
    $page_title = PortfolioPostType::COLLECTIVE_NOUN . ' Page';
    $menu_title = PortfolioPostType::COLLECTIVE_NOUN . ' Page';
    $parent_slug = 'edit.php?post_type=' . PortfolioPostType::ID;

    if (function_exists('acf_add_options_sub_page')) {
      acf_add_options_sub_page([
        'page_title' => $page_title,
        'menu_title' => $menu_title,
        'parent_slug' => $parent_slug,
      ]);
    }
  }

  public function create_terms() {
    foreach (self::all() as $portfolio_piece) {
      $this->create_related_portfolio_term($portfolio_piece);
    }
  }

  public function create_filters($post_type) {
    if (!self::in_media_library()) {
      return;
    }

    $filtered_slug = self::filter_query_arg() ?? '';

    $portfolio_pieces = Portfolio::all();
    sort($portfolio_pieces);

    $options = [];

    array_map(function ($piece) use (&$options, $filtered_slug) {
      $selected = self::selected($filtered_slug, $piece->slug());
      $slug = $piece->slug();
      $name = $piece->name();

      $options[] = "<option value=\"$slug\" $selected>$name</option>";
    }, $portfolio_pieces);

    $markup = '<select name=' . self::QUERY_ARG . '>';
    $markup .= '<option value="0">All portfolio pieces</option>';
    $markup .= implode($options);
    $markup .= '</select>';
    $markup .= '&nbsp;';

    echo $markup;
  }

  public function filter_query($query) {
    if (!(is_admin() && $query->is_main_query())) {
      return $query;
    }

    if (!self::in_media_library()) {
      return $query;
    }

    if (!self::filter_query_arg()) {
      return $query;
    }

    if (self::filter_query_arg() === '0') {
      return $query;
    }

    $query->query_vars['tax_query'] = [
      [
        'taxonomy' => Portfolio::TAXONOMY_ID,
        'field' => 'slug',
        'terms' => self::filter_query_arg(),
      ],
    ];

    return $query;
  }

  public function set_fields_on_save($post_id) {
    if (!$this->will_set_on_save($post_id)) {
      return;
    }

    $piece = new PortfolioPiece($post_id);

    $this->set_post_data($piece);
    $this->set_post_thumbnail($piece);
    $this->create_related_portfolio_piece_term($piece);
  }

  public function destroy_terms($post_id) {
    if (!$this->will_set_on_save($post_id)) {
      return;
    }

    $piece = new PortfolioPiece($post_id);

    $this->delete_related_portfolio_piece_term($piece);
  }

  private function will_set_on_save($id) {
    return (get_post_type($id) == PortfolioPostType::ID ? true : false);
  }

  private function set_post_data($portfolio_piece) {
    wp_update_post([
      'ID'         => $portfolio_piece->id(),
      'post_name'  => sanitize_title($portfolio_piece->name()),
      'post_title' => $portfolio_piece->name(),
    ]);
  }

  private function set_post_thumbnail($portfolio_piece) {
    set_post_thumbnail($portfolio_piece->id(), $portfolio_piece->card_image());
  }

  private function create_related_portfolio_piece_term($portfolio_piece) {
    if (get_post_status($portfolio_piece->id()) === 'publish') {
      wp_insert_term($portfolio_piece->name(), self::TAXONOMY_ID);
    }
  }

  private function delete_related_portfolio_piece_term($portfolio_piece) {
    $term = get_term_by('name', $portfolio_piece->name(), self::TAXONOMY_ID);

    wp_delete_term($term->term_id, self::TAXONOMY_ID);
  }
}
