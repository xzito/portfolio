<?php

namespace Xzito\Portfolio;

class PortfolioPiece {
  private $id;
  private $name;
  private $card_image;
  private $banner;
  private $description;
  private $main_copy;
  private $quote;
  private $list;
  private $cta;

  public static function find_by_name($name) {
    $query = new \WP_Query([
      'posts_per_page' => 1,
      'post_type'      => PortfolioPostType::ID,
      'name'           => $name,
      'fields'         => 'ids',
    ]);

    $id = $query->posts[0];

    return new Portfolio($id);
  }

  public static function find_related_attachments($name) {
    $query = new \WP_Query([
      'nopaging'    => true,
      'post_type'   => 'attachment',
      'post_status' => 'inherit',
      'fields'      => 'ids',
      'tax_query'   => [
        [
          'taxonomy' => Portfolio::TAXONOMY_ID,
          'field' => 'name',
          'terms' => $name,
        ],
      ],
    ]);

    return $query->posts ?? [];
  }

  public function __construct($portfolio_piece_id = '') {
    $this->id = $portfolio_piece_id;
    $this->set_name();
    $this->set_banner();
    $this->set_description();
    $this->set_main_copy();
    $this->set_card_image();
    $this->set_quote();
    $this->set_list();
    $this->set_cta();
  }

  public function id() {
    return $this->id;
  }

  public function name() {
    return $this->name;
  }

  public function slug() {
    return get_post_field('post_name', $this->id);
  }

  public function link() {
    return get_post_permalink($this->id);
  }

  public function term() {
    return get_term_by('name', $this->name, Portfolio::TAXONOMY_ID);
  }

  public function description() {
    return $this->description;
  }

  public function card_image($size = 'thumbnail') {
    return wp_get_attachment_image_url($this->card_image, $size);
  }

  public function card_image_tag($size = 'thumbnail') {
    return wp_get_attachment_image($this->card_image, $size);
  }

  public function banner($size = 'full') {
    return wp_get_attachment_image_url($this->banner, $size);
  }

  public function main_copy() {
    $copy = $this->main_copy;

    $id = $copy['side_image'];
    $img_tag = wp_get_attachment_image($id, 'large');

    $copy['img_tag'] = $img_tag;

    return $copy;
  }

  public function main_image($size = 'full') {
    return wp_get_attachment_image_url($this->main_image, $size);
  }

  public function main_image_tag($size = 'full') {
    return wp_get_attachment_image($this->main_image, $size);
  }

  public function quote() {
    $quote = $this->quote;
    $img_url = wp_get_attachment_image_url($quote['background_image'], 'large');

    $quote['img_url'] = $img_url;

    return $quote;
  }

  public function list() {
    return $this->list;
  }

  public function cta($size = 'large') {
    $cta = $this->cta;

    $cta['image'] = wp_get_attachment_image_url($cta['image'], $size);

    return $cta;
  }

  private function set_name() {
    $default = 'Unnamed Portfolio Piece';

    $this->name = (get_field('portfolio_info', $this->id)['name'] ?: $default);
  }

  private function set_description() {
    $this->description =
      get_field('portfolio_info', $this->id)['short_description'];
  }

  private function set_main_copy() {
    $this->main_copy = get_field('portfolio_main_copy', $this->id);
  }

  private function set_card_image() {
    $this->card_image = get_field('portfolio_images', $this->id)['card'];
  }

  private function set_banner() {
    $this->banner = get_field('portfolio_images', $this->id)['banner'];
  }

  private function set_quote() {
    $this->quote = get_field('portfolio_quote', $this->id);
  }

  private function set_list() {
    $this->list = get_field('portfolio_list', $this->id);
  }

  private function set_cta() {
    $cta_settings   = get_field('portfolio_cta', $this->id);
    $overlay        = $cta_settings['overlay_color'];
    $overlay_colors = [
      'light' => '#F5F5F5',
      'blue'  => '#293583'
    ];
    $side_image_tag = wp_get_attachment_image($cta_settings['image'], '1200x0');
    $bg_image =
      wp_get_attachment_image_url($cta_settings['bg_image'], 'fullwidth');

    $cta_data = [
      'show' => $cta_settings['show'],
      'heading' => $cta_settings['heading'],
      'overlay_color' => $overlay,
      'overlay_hex' => $overlay_colors[$overlay],
      'text' => $cta_settings['text'],
      'button_text' => $cta_settings['button_text'],
      'link' => $cta_settings['link'],
      'side_image_tag' => $side_image_tag,
      'bg_image_url' => $bg_image,
    ];

    $this->cta = $cta_data;
  }
}
