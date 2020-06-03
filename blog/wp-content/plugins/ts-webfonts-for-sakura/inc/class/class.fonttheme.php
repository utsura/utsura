<?php

class TypeSquare_ST_Fonttheme {
	private static $fonttheme;
	private static $instance;

	private function __construct(){}

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			$c = __CLASS__;
			self::$instance = new $c();
		}
		return self::$instance;
	}

	public static function get_fonttheme() {
		static $fonttheme;

		$fonttheme = array(
			'basic' => array(
				'name'	=> 'ベーシック',
				'fonts' => array(
					'title'   => '見出ゴMB31',
					'lead'    => 'カクミン R',
					'content' => '新ゴ R',
					'bold'    => '新ゴ M',
				),
			),
			'news' => array(
				'name'	=> 'ニュース',
				'fonts' => array(
					'title'   => 'ゴシックMB101 B',
					'lead'    => 'カクミン R',
					'content' => 'UD新ゴ R',
					'bold'    => 'UD新ゴ M',
				),
			),
			'fashion' => array(
				'name'	=> 'ファッション',
				'fonts' => array(
					'title'   => '解ミン 宙 B',
					'lead'    => '丸フォーク M',
					'content' => 'フォーク R',
					'bold'    => 'フォーク M',
				),
			),
			'pop' => array(
				'name'	=> 'ポップ',
				'fonts' => array(
					'title'   => '新丸ゴ 太ライン',
					'lead'    => 'はるひ学園',
					'content' => 'じゅん 201',
					'bold'    => 'じゅん 501',
				),
			),
			'japan_style' => array(
				'name'	=> '和風',
				'fonts' => array(
					'title'   => '隷書101',
					'lead'    => '正楷書CB1',
					'content' => 'リュウミン R-KL',
					'bold'    => 'リュウミン M-KL',
				),
			),
			'modern' => array(
				'name'	=> 'モダン',
				'fonts' => array(
					'title'   => 'すずむし',
					'lead'    => 'トーキング',
					'content' => 'ナウ-GM',
					'bold'    => 'ナウ-GM',
				),
			),
			'novels' => array(
				'name'	=> '小説',
				'fonts' => array(
					'title'   => '見出ミンMA31',
					'lead'    => '解ミン 宙 B',
					'content' => 'A1明朝',
					'bold'    => 'A1明朝',
				),
			),
			'smartphone' => array(
				'name'	=> 'スマホ',
				'fonts' => array(
					'title'   => 'UD新ゴ M',
					'lead'    => '新丸ゴ R',
					'content' => 'UD新ゴ コンデンス90 L',
					'bold'    => 'UD新ゴ コンデンス90 M',
				),
			),
		);

		$options = get_option( 'typesquare_custom_theme' );
		if ( $options && isset( $options['theme'] ) && is_array( $options['theme'] ) ) {
			$fonttheme = $fonttheme + $options['theme'];
		}
		return $fonttheme;
	}

	public static function get_custom_theme_json() {
		$options = get_option( 'typesquare_custom_theme' );
		return json_encode($options['theme']);
	}
}
