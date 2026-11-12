<?php
/**
 * [sw_ksasi_riseset] 숏코드 + 날짜 이동 AJAX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SW_KSASI_Shortcode {

	const TAG    = 'sw_ksasi_riseset';
	const ACTION = 'sw_ksasi_riseset';

	/** 날짜 이동 허용 범위(오늘 기준 ±일) */
	const NAV_RANGE_DAYS = 400;

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'ajax' ) );
	}

	public static function register_assets() {
		wp_register_style( 'sw-ksasi-sync', SW_KSASI_URL . 'assets/css/sw-ksasi-sync.css', array(), SW_KSASI_VERSION );
		wp_register_script( 'sw-ksasi-sync', SW_KSASI_URL . 'assets/js/sw-ksasi-sync.js', array(), SW_KSASI_VERSION, true );
		wp_localize_script( 'sw-ksasi-sync', 'SW_KSASI', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => self::ACTION,
			'error'   => '데이터를 불러오지 못했습니다.',
		) );
	}

	/**
	 * 숏코드 속성 정규화
	 */
	private static function normalize( $atts ) {
		$atts = shortcode_atts( array(
			'location' => '',
			'lat'      => '',
			'lng'      => '',
			'label'    => '',
			'date'     => '',
			'type'     => 'both',
			'twilight' => 'no',
			'nav'      => 'yes',
			'title'    => '오늘의 해와 달',
			'theme'    => 'light',
		), (array) $atts, self::TAG );

		$a = array(
			'location' => sanitize_text_field( $atts['location'] ),
			'lat'      => is_numeric( $atts['lat'] ) ? (float) $atts['lat'] : '',
			'lng'      => is_numeric( $atts['lng'] ) ? (float) $atts['lng'] : '',
			'label'    => sanitize_text_field( $atts['label'] ),
			'type'     => in_array( $atts['type'], array( 'sun', 'moon', 'both' ), true ) ? $atts['type'] : 'both',
			'twilight' => 'yes' === strtolower( $atts['twilight'] ) ? 'yes' : 'no',
			'nav'      => 'no' === strtolower( $atts['nav'] ) ? 'no' : 'yes',
			'title'    => sanitize_text_field( $atts['title'] ),
			'theme'    => 'dark' === $atts['theme'] ? 'dark' : 'light',
		);

		// 위경도 범위 검증 (실패 시 지역명 조회로 대체)
		if ( '' === $a['lat'] || '' === $a['lng'] || abs( $a['lat'] ) > 90 || abs( $a['lng'] ) > 180 ) {
			$a['lat'] = '';
			$a['lng'] = '';
		}
		if ( '' !== $a['lat'] ) {
			// 서명(JSON) 왕복 시 부동소수 표현이 달라지지 않도록 문자열로 보관
			$a['lat'] = (string) round( $a['lat'], 6 );
			$a['lng'] = (string) round( $a['lng'], 6 );
		}
		if ( '' === $a['lat'] && '' === $a['location'] ) {
			$a['location'] = (string) sw_ksasi_get_setting( 'location' );
		}

		return array( $a, sw_ksasi_sanitize_date( $atts['date'] ) );
	}

	/**
	 * AJAX 요청 위변조 방지용 서명 (캐시된 페이지에서도 만료되지 않도록 nonce 대신 사용)
	 */
	private static function sign( array $a ) {
		ksort( $a );
		return wp_hash( wp_json_encode( $a ), 'nonce' );
	}

	public static function shortcode( $atts ) {
		list( $a, $date ) = self::normalize( $atts );

		// type="both" 이면 해/달 카드를 한 자리에 겹쳐 두고 클릭(터치)으로 전환한다.
		$flip = 'both' === $a['type'];

		wp_enqueue_style( 'sw-ksasi-sync' );
		if ( 'yes' === $a['nav'] || $flip ) {
			wp_enqueue_script( 'sw-ksasi-sync' );
		}

		return sprintf(
			'<div class="sw-ksasi sw-ksasi--%1$s%6$s"%7$s data-atts="%2$s" data-sig="%3$s" data-date="%4$s" aria-live="polite">%5$s</div>',
			esc_attr( $a['theme'] ),
			esc_attr( wp_json_encode( $a ) ),
			esc_attr( self::sign( $a ) ),
			esc_attr( $date ),
			self::render( $a, $date ),
			$flip ? ' sw-ksasi--flip' : '',
			$flip ? ' data-view="sun"' : ''
		);
	}

	public static function ajax() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- 서명(sig)으로 검증
		$raw  = isset( $_POST['atts'] ) ? json_decode( wp_unslash( $_POST['atts'] ), true ) : null;
		$sig  = isset( $_POST['sig'] ) ? sanitize_text_field( wp_unslash( $_POST['sig'] ) ) : '';
		$date = isset( $_POST['date'] ) ? sw_ksasi_sanitize_date( wp_unslash( $_POST['date'] ) ) : '';
		// phpcs:enable

		if ( ! is_array( $raw ) || ! hash_equals( self::sign( $raw ), $sig ) ) {
			wp_send_json_error( array( 'message' => '잘못된 요청입니다.' ), 400 );
		}

		$today  = new DateTimeImmutable( wp_date( 'Y-m-d' ), wp_timezone() );
		$target = DateTimeImmutable::createFromFormat( '!Ymd', $date, wp_timezone() );
		if ( ! $target || abs( (int) $today->diff( $target )->format( '%r%a' ) ) > self::NAV_RANGE_DAYS ) {
			wp_send_json_error( array( 'message' => '조회할 수 없는 날짜입니다.' ), 400 );
		}

		wp_send_json_success( array(
			'html' => self::render( $raw, $date ),
			'date' => $date,
		) );
	}

	/**
	 * 위젯 내부 HTML
	 */
	public static function render( array $a, $date ) {
		$data = '' !== $a['lat']
			? SW_KSASI_API::get_coords( $a['lat'], $a['lng'], $date )
			: SW_KSASI_API::get_area( $a['location'], $date );

		$tz       = wp_timezone();
		$dt       = DateTimeImmutable::createFromFormat( '!Ymd', $date, $tz );
		$weekdays = array( '일', '월', '화', '수', '목', '금', '토' );
		$is_today = wp_date( 'Ymd' ) === $date;

		$place = '' !== $a['label'] ? $a['label'] : ( '' !== $a['lat'] ? sprintf( '%.4f, %.4f', (float) $a['lat'], (float) $a['lng'] ) : $a['location'] );

		ob_start();
		?>
		<div class="sw-ksasi__head">
			<div class="sw-ksasi__titles">
				<?php if ( '' !== $a['title'] ) : ?>
					<div class="sw-ksasi__title"><?php echo esc_html( $a['title'] ); ?></div>
				<?php endif; ?>
				<div class="sw-ksasi__meta">
					<span class="sw-ksasi__place"><?php echo esc_html( $place ); ?></span>
					<span class="sw-ksasi__date"><?php echo esc_html( sprintf( '%s (%s)', $dt->format( 'Y.m.d' ), $weekdays[ (int) $dt->format( 'w' ) ] ) ); ?></span>
				</div>
			</div>
			<?php if ( 'yes' === $a['nav'] ) : ?>
				<div class="sw-ksasi__nav">
					<button type="button" class="sw-ksasi__btn" data-shift="-1" aria-label="이전 날짜">&lsaquo;</button>
					<?php if ( ! $is_today ) : ?>
						<button type="button" class="sw-ksasi__btn sw-ksasi__btn--today" data-shift="0">오늘</button>
					<?php endif; ?>
					<button type="button" class="sw-ksasi__btn" data-shift="1" aria-label="다음 날짜">&rsaquo;</button>
				</div>
			<?php endif; ?>
		</div>
		<?php

		if ( is_wp_error( $data ) ) {
			$msg = current_user_can( 'manage_options' ) ? $data->get_error_message() : '출몰시각 정보를 불러올 수 없습니다.';
			echo '<div class="sw-ksasi__error">' . esc_html( $msg ) . '</div>';
			return ob_get_clean();
		}

		$t = array();
		foreach ( array( 'sunrise', 'suntransit', 'sunset', 'moonrise', 'moontransit', 'moonset', 'civilm', 'civile', 'nautm', 'naute', 'astm', 'aste' ) as $k ) {
			$t[ $k ] = sw_ksasi_format_time( $data[ $k ] ?? '' );
		}
		$dash = '<span class="sw-ksasi__none" title="해당 날짜에 현상 없음">—</span>';
		$show = function ( $v ) use ( $dash ) {
			return '' === $v ? $dash : esc_html( $v );
		};

		// 오늘이면 현재 태양 위치(일출~일몰 사이 비율) 계산
		$progress = null;
		if ( $is_today && '' !== $t['sunrise'] && '' !== $t['sunset'] ) {
			$now  = (int) wp_date( 'G' ) * 60 + (int) wp_date( 'i' );
			$rise = (int) substr( $t['sunrise'], 0, 2 ) * 60 + (int) substr( $t['sunrise'], 3, 2 );
			$set  = (int) substr( $t['sunset'], 0, 2 ) * 60 + (int) substr( $t['sunset'], 3, 2 );
			if ( $set > $rise ) {
				$progress = max( 0, min( 1, ( $now - $rise ) / ( $set - $rise ) ) );
			}
		}
		?>
		<?php $flip = 'both' === $a['type']; ?>
		<div class="sw-ksasi__body">
			<?php if ( $flip ) : ?>
			<div class="sw-ksasi__cards">
			<?php endif; ?>
			<?php if ( 'moon' !== $a['type'] ) : ?>
				<section class="sw-ksasi__card sw-ksasi__card--sun" aria-label="일출·일몰">
					<?php if ( $flip ) : ?>
						<button type="button" class="sw-ksasi__switch" data-view="moon" aria-label="월출·월몰 보기로 전환">월출·월몰 <span aria-hidden="true">&rsaquo;</span></button>
					<?php endif; ?>
					<div class="sw-ksasi__arc" aria-hidden="true">
						<svg viewBox="0 0 200 100" preserveAspectRatio="xMidYMax meet">
							<path class="sw-ksasi__arc-track" d="M10 95 A90 90 0 0 1 190 95" />
							<?php
							if ( null !== $progress ) :
								$angle = M_PI * ( 1 - $progress );
								$cx    = 100 + 90 * cos( $angle );
								$cy    = 95 - 90 * sin( $angle );
								?>
								<path class="sw-ksasi__arc-done" d="M10 95 A90 90 0 0 1 <?php echo esc_attr( round( $cx, 2 ) . ' ' . round( $cy, 2 ) ); ?>" />
								<circle class="sw-ksasi__arc-sun" cx="<?php echo esc_attr( round( $cx, 2 ) ); ?>" cy="<?php echo esc_attr( round( $cy, 2 ) ); ?>" r="7" />
							<?php endif; ?>
							<line class="sw-ksasi__arc-horizon" x1="0" y1="95" x2="200" y2="95" />
						</svg>
					</div>
					<div class="sw-ksasi__row">
						<div class="sw-ksasi__item">
							<span class="sw-ksasi__label">일출</span>
							<span class="sw-ksasi__time"><?php echo $show( $t['sunrise'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
						<div class="sw-ksasi__item">
							<span class="sw-ksasi__label">남중</span>
							<span class="sw-ksasi__time sw-ksasi__time--sub"><?php echo $show( $t['suntransit'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
						<div class="sw-ksasi__item">
							<span class="sw-ksasi__label">일몰</span>
							<span class="sw-ksasi__time"><?php echo $show( $t['sunset'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
					</div>
					<?php $len = sw_ksasi_duration( $t['sunrise'], $t['sunset'] ); ?>
					<?php if ( '' !== $len ) : ?>
						<div class="sw-ksasi__foot">낮의 길이 <strong><?php echo esc_html( $len ); ?></strong></div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( 'sun' !== $a['type'] ) : ?>
				<section class="sw-ksasi__card sw-ksasi__card--moon" aria-label="월출·월몰">
					<?php if ( $flip ) : ?>
						<button type="button" class="sw-ksasi__switch" data-view="sun" aria-label="일출·일몰 보기로 전환">일출·일몰 <span aria-hidden="true">&rsaquo;</span></button>
					<?php endif; ?>
					<?php
					// 명암 모양은 천문 계산값, 월령 표기는 KASI 월령 API 값을 우선 사용한다.
					$moon       = sw_ksasi_calc_moon( $date );
					$api_age    = SW_KSASI_API::get_lunar_age( $date );
					$moon_age   = null !== $api_age ? $api_age : $moon['age'];
					$moon_name  = sw_ksasi_phase_name( $moon_age );
					$moon_illum = (int) round( $moon['illum'] * 100 );
					?>
					<div class="sw-ksasi__moon">
						<?php echo sw_ksasi_moon_svg( $moon['illum'], $moon['waxing'], sprintf( '%s, 밝기 %d%%', $moon_name, $moon_illum ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<div class="sw-ksasi__moon-info">
							<strong class="sw-ksasi__moon-name"><?php echo esc_html( $moon_name ); ?></strong>
							<span class="sw-ksasi__moon-meta">월령 <?php echo esc_html( number_format( $moon_age, 1 ) ); ?>일 · 밝기 <?php echo esc_html( $moon_illum ); ?>%</span>
						</div>
					</div>
					<div class="sw-ksasi__row">
						<div class="sw-ksasi__item">
							<span class="sw-ksasi__label">월출</span>
							<span class="sw-ksasi__time"><?php echo $show( $t['moonrise'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
						<div class="sw-ksasi__item">
							<span class="sw-ksasi__label">남중</span>
							<span class="sw-ksasi__time sw-ksasi__time--sub"><?php echo $show( $t['moontransit'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
						<div class="sw-ksasi__item">
							<span class="sw-ksasi__label">월몰</span>
							<span class="sw-ksasi__time"><?php echo $show( $t['moonset'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
					</div>
				</section>
			<?php endif; ?>
			<?php if ( $flip ) : ?>
			</div>
			<?php endif; ?>

			<?php if ( 'yes' === $a['twilight'] ) : ?>
				<table class="sw-ksasi__twilight">
					<thead><tr><th>박명</th><th>아침 시작</th><th>저녁 종료</th></tr></thead>
					<tbody>
						<tr><th>시민박명</th><td><?php echo $show( $t['civilm'] ); // phpcs:ignore ?></td><td><?php echo $show( $t['civile'] ); // phpcs:ignore ?></td></tr>
						<tr><th>항해박명</th><td><?php echo $show( $t['nautm'] ); // phpcs:ignore ?></td><td><?php echo $show( $t['naute'] ); // phpcs:ignore ?></td></tr>
						<tr><th>천문박명</th><td><?php echo $show( $t['astm'] ); // phpcs:ignore ?></td><td><?php echo $show( $t['aste'] ); // phpcs:ignore ?></td></tr>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<div class="sw-ksasi__credit">자료: 한국천문연구원<?php echo 'sun' !== $a['type'] ? ' · 달 이미지: NASA SVS' : ''; ?></div>
		<?php
		return ob_get_clean();
	}
}
