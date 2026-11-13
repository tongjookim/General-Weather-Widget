<?php
/**
 * [sw_kma_weather] — 기상청 지상관측 날씨 페이지
 *
 * 구성: 현재 날씨 + 평년 비교, 최근 24시간 그래프, 대기질, 생활 예보, 최근 7일, 해와 달, 전국 관측 현황
 * 지점 변경은 ?kma_stn=번호 (GET) 로 처리해 JS 없이도 동작한다.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SW_KMA_Page {

	const TAG   = 'sw_kma_weather';
	const QUERY = 'kma_stn';

	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'sw-kma-weather', SW_KSASI_URL . 'assets/css/sw-kma-weather.css', array(), SW_KSASI_VERSION );
		wp_register_script( 'sw-kma-weather', SW_KSASI_URL . 'assets/js/sw-kma-weather.js', array(), SW_KSASI_VERSION, true );
	}

	/**
	 * 날씨 페이지 생성 (이미 있으면 그 ID 반환)
	 *
	 * @param string $status publish | draft.
	 * @return int|WP_Error
	 */
	public static function create_page( $status = 'draft' ) {
		$id   = (int) sw_ksasi_get_setting( 'kma_page_id' );
		$page = $id ? get_post( $id ) : null;
		if ( $page && 'trash' !== $page->post_status ) {
			return $id;
		}
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => $status,
			'post_title'   => '날씨',
			'post_name'    => 'weather',
			'post_content' => '<!-- wp:shortcode -->[' . self::TAG . ']<!-- /wp:shortcode -->',
		), true );
		if ( ! is_wp_error( $id ) ) {
			$s                = sw_ksasi_get_setting();
			$s['kma_page_id'] = $id;
			update_option( SW_KSASI_OPTION, $s );
		}
		return $id;
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'stn' => '' ), (array) $atts, self::TAG );

		// 지점: URL 인자 > 숏코드 속성 > 설정 기본값
		$stn = isset( $_GET[ self::QUERY ] ) ? absint( $_GET[ self::QUERY ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! SW_KMA_Data::station( $stn ) ) {
			$stn = absint( $atts['stn'] );
		}
		if ( ! SW_KMA_Data::station( $stn ) ) {
			$stn = (int) sw_ksasi_get_setting( 'kma_stn' );
		}
		if ( ! SW_KMA_Data::station( $stn ) ) {
			$stn = 108;
		}

		wp_enqueue_style( 'sw-kma-weather' );
		wp_enqueue_style( 'sw-ksasi-sync' ); // 달 위상 그래픽 스타일 재사용
		wp_enqueue_script( 'sw-kma-weather' );

		ob_start();
		echo '<div class="sw-kma">';
		self::render_header( $stn );

		$hourly = SW_KMA_API::hourly( $stn, 24 );
		if ( is_wp_error( $hourly ) ) {
			self::render_error( $hourly );
			echo '</div>';
			return ob_get_clean();
		}

		self::render_current( $stn, $hourly );
		self::render_chart( $hourly );
		$air = self::render_air( $stn );
		self::render_life( $hourly, $air );
		self::render_daily( $stn );
		self::render_astro( $stn );
		self::render_nation( $stn );

		echo '<p class="sw-kma__credit">자료: 기상청 API허브 지상관측(ASOS), 환경부·한국환경공단 에어코리아, 한국천문연구원 출몰시각·월령 정보, 달 이미지 NASA SVS. 관측값과 대기질 측정값은 품질검사 전 자료로 실제와 다를 수 있습니다.</p>';
		echo '</div>';
		return ob_get_clean();
	}

	/* ── 공통 ─────────────────────────────────────────── */

	private static function render_error( WP_Error $e ) {
		$msg = current_user_can( 'manage_options' ) ? $e->get_error_message() : '관측자료를 불러올 수 없습니다. 잠시 후 다시 시도해 주세요.';
		echo '<div class="sw-kma__error">' . esc_html( $msg ) . '</div>';
	}

	private static function fmt( $v, $dec = 1, $unit = '' ) {
		return null === $v ? '-' : number_format( (float) $v, $dec ) . $unit;
	}

	private static function station_url( $stn ) {
		return add_query_arg( self::QUERY, (int) $stn, get_permalink() ) . '#sw-kma';
	}

	private static function is_night( $tm ) {
		$h = (int) substr( (string) $tm, 8, 2 );
		return $h < 6 || $h >= 18;
	}

	/**
	 * 체감온도 (기상청 겨울철 공식: 기온 10℃ 이하, 풍속 1.3m/s 이상)
	 */
	private static function wind_chill( $ta, $ws ) {
		if ( null === $ta || null === $ws || $ta > 10 || $ws < 1.3 ) {
			return null;
		}
		$v = pow( $ws * 3.6, 0.16 );
		return 13.12 + 0.6215 * $ta - 11.37 * $v + 0.3965 * $ta * $v;
	}

	/* ── 헤더 + 지점 선택 ─────────────────────────────── */

	private static function render_header( $stn ) {
		$st     = SW_KMA_Data::station( $stn );
		$groups = array();
		foreach ( SW_KMA_Data::stations() as $id => $s ) {
			$groups[ $s['region'] ][ $id ] = $s['name'];
		}
		?>
		<header class="sw-kma__head" id="sw-kma">
			<div>
				<h2 class="sw-kma__title"><?php echo esc_html( $st['name'] ); ?> 날씨</h2>
				<p class="sw-kma__sub"><?php echo esc_html( $st['region'] ); ?> · 지점번호 <?php echo esc_html( $stn ); ?> · 기상청 종관기상관측</p>
			</div>
			<form class="sw-kma__picker" method="get" action="<?php echo esc_url( get_permalink() ); ?>#sw-kma">
				<label class="screen-reader-text" for="sw-kma-stn">관측지점 선택</label>
				<select id="sw-kma-stn" name="<?php echo esc_attr( self::QUERY ); ?>" data-autosubmit>
					<?php foreach ( $groups as $region => $items ) : ?>
						<optgroup label="<?php echo esc_attr( $region ); ?>">
							<?php foreach ( $items as $id => $name ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $stn, $id ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
				<noscript><button type="submit">보기</button></noscript>
			</form>
		</header>
		<?php
	}

	/* ── ① 현재 날씨 ──────────────────────────────────── */

	private static function render_current( $stn, array $hourly ) {
		$cur = null;
		for ( $i = count( $hourly ) - 1; $i >= 0; $i-- ) {
			if ( null !== $hourly[ $i ]['TA'] ) {
				$cur = $hourly[ $i ];
				break;
			}
		}
		if ( ! $cur ) {
			echo '<div class="sw-kma__error">최근 관측자료가 없습니다.</div>';
			return;
		}

		// 오늘(0시 이후) 최고/최저
		$today = substr( $cur['TM'], 0, 8 );
		$tmax  = null;
		$tmin  = null;
		foreach ( $hourly as $r ) {
			if ( null === $r['TA'] || substr( $r['TM'], 0, 8 ) !== $today ) {
				continue;
			}
			$tmax = null === $tmax ? $r['TA'] : max( $tmax, $r['TA'] );
			$tmin = null === $tmin ? $r['TA'] : min( $tmin, $r['TA'] );
		}

		// 1시간 전 대비
		$prev = null;
		foreach ( $hourly as $r ) {
			if ( null !== $r['TA'] && $r['TM'] < $cur['TM'] ) {
				$prev = $r;
			}
		}
		$delta = $prev ? $cur['TA'] - $prev['TA'] : null;

		$wx     = SW_KMA_Data::weather( $cur, self::is_night( $cur['TM'] ) );
		$deg    = SW_KMA_Data::wd_to_deg( $cur['WD'] );
		$chill  = self::wind_chill( $cur['TA'], $cur['WS'] );
		$normal = SW_KMA_API::normal( $stn, (int) substr( $today, 4, 2 ), (int) substr( $today, 6, 2 ) );
		$normal = is_wp_error( $normal ) ? null : $normal;
		?>
		<section class="sw-kma__now" aria-label="현재 날씨">
			<div class="sw-kma__now-main">
				<div class="sw-kma__now-icon"><?php echo SW_KMA_Data::icon( $wx['icon'], 72 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<div>
					<div class="sw-kma__now-temp"><?php echo esc_html( self::fmt( $cur['TA'] ) ); ?><span>℃</span></div>
					<div class="sw-kma__now-desc">
						<?php if ( '' !== $wx['label'] ) : ?><strong><?php echo esc_html( $wx['label'] ); ?></strong><?php endif; ?>
						<?php if ( null !== $delta ) : ?>
							<span class="sw-kma__delta <?php echo $delta > 0 ? 'is-up' : ( $delta < 0 ? 'is-down' : '' ); ?>">1시간 전보다 <?php echo esc_html( ( $delta > 0 ? '+' : '' ) . number_format( $delta, 1 ) ); ?>℃</span>
						<?php endif; ?>
					</div>
					<div class="sw-kma__now-range">
						오늘 최고 <b class="t-max"><?php echo esc_html( self::fmt( $tmax ) ); ?>℃</b> · 최저 <b class="t-min"><?php echo esc_html( self::fmt( $tmin ) ); ?>℃</b>
						<?php if ( null !== $chill ) : ?> · 체감 <b><?php echo esc_html( self::fmt( $chill ) ); ?>℃</b><?php endif; ?>
					</div>
					<div class="sw-kma__now-time"><?php echo esc_html( SW_KMA_Data::format_tm( $cur['TM'], true ) ); ?> 관측</div>
				</div>
			</div>

			<dl class="sw-kma__stats">
				<div><dt>습도</dt><dd><?php echo esc_html( self::fmt( $cur['HM'], 0, '%' ) ); ?></dd></div>
				<div><dt>바람</dt><dd>
					<?php if ( null !== $deg && null !== $cur['WS'] && $cur['WS'] > 0 ) : ?>
						<svg class="sw-kma__arrow" viewBox="0 0 16 16" style="transform:rotate(<?php echo esc_attr( (int) $deg + 180 ); ?>deg)" aria-hidden="true"><path d="M8 1l4 10-4-2.5L4 11z" fill="currentColor"/></svg>
						<?php echo esc_html( SW_KMA_Data::wind_dir( $deg ) . ' ' ); ?>
					<?php endif; ?>
					<?php echo esc_html( self::fmt( $cur['WS'], 1, 'm/s' ) ); ?>
				</dd></div>
				<div><dt>오늘 강수량</dt><dd><?php echo esc_html( null === $cur['RN_DAY'] ? '0mm' : self::fmt( $cur['RN_DAY'], 1, 'mm' ) ); ?></dd></div>
				<div><dt>해면기압</dt><dd><?php echo esc_html( self::fmt( $cur['PS'], 1, 'hPa' ) ); ?></dd></div>
				<div><dt>이슬점</dt><dd><?php echo esc_html( self::fmt( $cur['TD'], 1, '℃' ) ); ?></dd></div>
				<?php if ( null !== $cur['VS'] ) : ?>
					<div><dt>시정</dt><dd><?php echo esc_html( self::fmt( $cur['VS'] / 100, 1, 'km' ) ); ?></dd></div>
				<?php endif; ?>
				<?php if ( null !== $cur['CA_TOT'] ) : ?>
					<div><dt>전운량</dt><dd><?php echo esc_html( self::fmt( $cur['CA_TOT'], 0, '/10' ) ); ?></dd></div>
				<?php endif; ?>
				<?php if ( ! empty( $cur['SD_TOT'] ) ) : ?>
					<div><dt>적설</dt><dd><?php echo esc_html( self::fmt( $cur['SD_TOT'], 1, 'cm' ) ); ?></dd></div>
				<?php endif; ?>
			</dl>

			<?php if ( $normal && null !== $normal['TA_MAX'] ) : ?>
				<div class="sw-kma__normal">
					<span class="sw-kma__normal-label">평년(1991~2020) 오늘</span>
					최고 <b><?php echo esc_html( self::fmt( $normal['TA_MAX'] ) ); ?>℃</b>
					최저 <b><?php echo esc_html( self::fmt( $normal['TA_MIN'] ) ); ?>℃</b>
					평균 <b><?php echo esc_html( self::fmt( $normal['TA'] ) ); ?>℃</b>
					<?php
					// 오후 3시 이후에는 오늘 최고기온을, 그 전에는 현재 기온을 평년 일평균과 비교한다.
					$late = (int) substr( $cur['TM'], 8, 2 ) >= 15;
					$base = $late ? $normal['TA_MAX'] : $normal['TA'];
					$obs  = $late ? $tmax : $cur['TA'];
					if ( null !== $base && null !== $obs ) :
						$diff = $obs - $base;
						?>
						<span class="sw-kma__normal-diff <?php echo $diff >= 0 ? 'is-up' : 'is-down'; ?>">
							<?php echo esc_html( $late ? '오늘 최고기온은 평년 최고보다' : '지금 기온은 평년 일평균보다' ); ?>
							<?php echo esc_html( number_format( abs( $diff ), 1 ) ); ?>℃ <?php echo $diff >= 0 ? '높음' : '낮음'; ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/* ── ② 최근 24시간 그래프 (서버 렌더 SVG) ───────────── */

	private static function render_chart( array $rows ) {
		$rows = array_values( array_filter( $rows, function ( $r ) {
			return '00' === substr( $r['TM'], 10, 2 ); // 정시 자료만
		} ) );
		$rows = array_slice( $rows, -25 );
		$n    = count( $rows );
		if ( $n < 2 ) {
			return;
		}

		$temps = array_filter( array_column( $rows, 'TA' ), 'is_numeric' );
		if ( ! $temps ) {
			return;
		}
		$rain  = array_map( function ( $r ) {
			return null === $r['RN'] ? 0 : (float) $r['RN'];
		}, $rows );
		$t_min = floor( min( $temps ) ) - 1;
		$t_max = ceil( max( $temps ) ) + 1;
		$r_max = max( 5, max( $rain ) ) / 0.35; // 강수 막대는 그래프 높이의 35% 이내

		// 좌표계
		$w  = 720;
		$h  = 240;
		$pl = 36;
		$pr = 36;
		$pt = 24;
		$pb = 32;
		$cw = $w - $pl - $pr;
		$ch = $h - $pt - $pb;
		$x  = function ( $i ) use ( $pl, $cw, $n ) {
			return $pl + $cw * $i / ( $n - 1 );
		};
		$y  = function ( $t ) use ( $pt, $ch, $t_min, $t_max ) {
			return $pt + $ch * ( 1 - ( $t - $t_min ) / ( $t_max - $t_min ) );
		};

		$pts = array();
		foreach ( $rows as $i => $r ) {
			if ( null !== $r['TA'] ) {
				$pts[] = round( $x( $i ), 1 ) . ',' . round( $y( $r['TA'] ), 1 );
			}
		}
		$area = $pts ? 'M' . explode( ',', $pts[0] )[0] . ',' . ( $pt + $ch ) . ' L' . implode( ' L', $pts ) . ' L' . explode( ',', end( $pts ) )[0] . ',' . ( $pt + $ch ) . ' Z' : '';

		$step  = max( 1, (int) ceil( ( $t_max - $t_min ) / 4 ) );
		$bar_w = max( 4, $cw / $n * 0.55 );
		?>
		<section class="sw-kma__card" aria-labelledby="sw-kma-chart-title">
			<div class="sw-kma__card-head">
				<h3 id="sw-kma-chart-title">최근 24시간</h3>
				<div class="sw-kma__legend"><span class="lg-temp">기온(℃)</span><span class="lg-rain">강수량(mm)</span></div>
			</div>
			<div class="sw-kma__chart-wrap">
				<svg class="sw-kma__chart" viewBox="0 0 <?php echo (int) $w; ?> <?php echo (int) $h; ?>" role="img" aria-label="최근 24시간 기온과 강수량 그래프">
					<defs>
						<linearGradient id="sw-kma-area" x1="0" y1="0" x2="0" y2="1">
							<stop class="area-stop" offset="0" stop-opacity=".25" />
							<stop class="area-stop" offset="1" stop-opacity="0" />
						</linearGradient>
					</defs>
					<?php for ( $t = (int) ( ceil( $t_min / $step ) * $step ); $t <= $t_max; $t += $step ) : ?>
						<line class="grid" x1="<?php echo (int) $pl; ?>" x2="<?php echo (int) ( $w - $pr ); ?>" y1="<?php echo esc_attr( round( $y( $t ), 1 ) ); ?>" y2="<?php echo esc_attr( round( $y( $t ), 1 ) ); ?>" />
						<text class="axis" x="<?php echo (int) ( $pl - 6 ); ?>" y="<?php echo esc_attr( round( $y( $t ), 1 ) + 4 ); ?>" text-anchor="end"><?php echo esc_html( $t ); ?>°</text>
					<?php endfor; ?>

					<?php foreach ( $rain as $i => $rn ) : ?>
						<?php
						if ( $rn <= 0 ) {
							continue;
						}
						$bh = $ch * $rn / $r_max;
						?>
						<rect class="rain" x="<?php echo esc_attr( round( $x( $i ) - $bar_w / 2, 1 ) ); ?>" y="<?php echo esc_attr( round( $pt + $ch - $bh, 1 ) ); ?>" width="<?php echo esc_attr( round( $bar_w, 1 ) ); ?>" height="<?php echo esc_attr( round( $bh, 1 ) ); ?>" rx="2"><title><?php echo esc_html( SW_KMA_Data::format_tm( $rows[ $i ]['TM'] ) . ' 강수 ' . $rn . 'mm' ); ?></title></rect>
					<?php endforeach; ?>

					<g class="temp">
						<path class="area" d="<?php echo esc_attr( $area ); ?>" fill="url(#sw-kma-area)" />
						<polyline class="line" points="<?php echo esc_attr( implode( ' ', $pts ) ); ?>" />
						<?php foreach ( $rows as $i => $r ) : ?>
							<?php
							if ( null === $r['TA'] ) {
								continue;
							}
							$is_label = 0 === ( $n - 1 - $i ) % 3;
							?>
							<circle class="dot<?php echo $i === $n - 1 ? ' is-now' : ''; ?>" cx="<?php echo esc_attr( round( $x( $i ), 1 ) ); ?>" cy="<?php echo esc_attr( round( $y( $r['TA'] ), 1 ) ); ?>" r="<?php echo $i === $n - 1 ? 4.5 : 2.5; ?>"><title><?php echo esc_html( SW_KMA_Data::format_tm( $r['TM'] ) . ' ' . $r['TA'] . '℃ · 습도 ' . self::fmt( $r['HM'], 0, '%' ) ); ?></title></circle>
							<?php if ( $is_label ) : ?>
								<text class="val" x="<?php echo esc_attr( round( $x( $i ), 1 ) ); ?>" y="<?php echo esc_attr( round( $y( $r['TA'] ) - 9, 1 ) ); ?>" text-anchor="middle"><?php echo esc_html( number_format( $r['TA'], 1 ) ); ?></text>
							<?php endif; ?>
						<?php endforeach; ?>
					</g>

					<?php foreach ( $rows as $i => $r ) : ?>
						<?php if ( 0 === ( $n - 1 - $i ) % 3 ) : ?>
							<text class="axis" x="<?php echo esc_attr( round( $x( $i ), 1 ) ); ?>" y="<?php echo (int) ( $h - 10 ); ?>" text-anchor="middle"><?php echo esc_html( (int) substr( $r['TM'], 8, 2 ) . '시' ); ?></text>
						<?php endif; ?>
					<?php endforeach; ?>
				</svg>
			</div>
		</section>
		<?php
	}

	/* ── 대기질 (에어코리아) ─────────────────────────────── */

	/**
	 * @return array|null 대기질 자료 (생활 예보에서 재사용)
	 */
	private static function render_air( $stn ) {
		$region = SW_KMA_Data::air_region( $stn );
		if ( ! $region ) {
			return null;
		}
		$coords = SW_KMA_API::station_coords();
		$lat    = isset( $coords[ $stn ] ) ? $coords[ $stn ]['lat'] : null;
		$lon    = isset( $coords[ $stn ] ) ? $coords[ $stn ]['lon'] : null;
		$air    = SW_Air_API::for_location( $lat, $lon, $region['sido'] );
		if ( is_wp_error( $air ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				self::render_error( $air );
			}
			return null;
		}
		$fc = SW_Air_API::forecast();

		$grades = SW_Air_API::GRADES;
		$time   = strlen( $air['time'] ) >= 16 ? substr( $air['time'], 11, 5 ) : $air['time'];
		if ( 'nearest' === $air['mode'] ) {
			$basis = sprintf( '%s 측정소(관측지점에서 %.1fkm) · %s 기준', $air['label'], $air['dist'], $time );
		} else {
			$basis = sprintf( '%s 도시대기 측정소 %d곳 평균 · %s 기준', $air['label'], $air['count'], $time );
		}

		// 오염물질 막대 위치: 등급 구간(4칸) 안에서의 상대 위치
		$gauge = function ( $item, $v ) {
			if ( null === $v ) {
				return null;
			}
			$b  = SW_Air_API::ITEMS[ $item ][3];
			$lo = 0;
			foreach ( array( $b[0], $b[1], $b[2], $b[2] * 2 ) as $i => $hi ) {
				if ( $v <= $hi || 3 === $i ) {
					return min( 100, ( $i + min( 1, ( $v - $lo ) / ( $hi - $lo ) ) ) / 4 * 100 );
				}
				$lo = $hi;
			}
			return 100;
		};
		$val = function ( $item, $v ) {
			return null === $v ? '-' : number_format( (float) $v, SW_Air_API::ITEMS[ $item ][2] );
		};
		?>
		<section class="sw-kma__card sw-kma__air" aria-labelledby="sw-kma-air-title">
			<div class="sw-kma__card-head">
				<h3 id="sw-kma-air-title">대기질</h3>
				<span class="sw-kma__muted"><?php echo esc_html( $basis ); ?></span>
			</div>

			<div class="sw-kma__air-pm">
				<?php foreach ( array( 'pm10', 'pm25' ) as $k ) : ?>
					<?php
					$g   = SW_Air_API::grade( $k, $air[ $k ] );
					$pos = $gauge( $k, $air[ $k ] );
					?>
					<div class="sw-kma__air-pmrow g<?php echo esc_attr( (int) $g ); ?>">
						<div class="sw-kma__air-pmhead">
							<span><?php echo esc_html( SW_Air_API::ITEMS[ $k ][0] ); ?> <small><?php echo 'pm10' === $k ? 'PM10' : 'PM2.5'; ?></small></span>
							<span class="sw-kma__air-badge"><?php echo esc_html( $g ? $grades[ $g ] : '-' ); ?></span>
						</div>
						<p class="sw-kma__air-value"><b><?php echo esc_html( $val( $k, $air[ $k ] ) ); ?></b> ㎍/㎥
							<?php if ( null !== $air[ $k . '_24' ] ) : ?><span class="sw-kma__muted">· 24시간 평균 <?php echo esc_html( $val( $k, $air[ $k . '_24' ] ) ); ?></span><?php endif; ?>
						</p>
						<div class="sw-kma__air-gauge" aria-hidden="true"><i></i><i></i><i></i><i></i><?php if ( null !== $pos ) : ?><b style="left:<?php echo esc_attr( round( $pos, 1 ) ); ?>%"></b><?php endif; ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<dl class="sw-kma__air-gases">
				<?php foreach ( array( 'o3', 'no2', 'co', 'so2' ) as $k ) : ?>
					<?php $g = SW_Air_API::grade( $k, $air[ $k ] ); ?>
					<div class="g<?php echo esc_attr( (int) $g ); ?>">
						<dt><?php echo esc_html( SW_Air_API::ITEMS[ $k ][0] ); ?> <span class="sw-kma__air-badge"><?php echo esc_html( $g ? $grades[ $g ] : '-' ); ?></span></dt>
						<dd><b><?php echo esc_html( $val( $k, $air[ $k ] ) ); ?></b> <?php echo esc_html( SW_Air_API::ITEMS[ $k ][1] ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>

			<?php if ( ! is_wp_error( $fc ) && $fc['days'] ) : ?>
				<?php
				$labels = array( '오늘', '내일', '모레' );
				$today  = new DateTimeImmutable( 'today', wp_timezone() );
				?>
				<div class="sw-kma__air-fc">
					<h4>예보 <span class="sw-kma__muted"><?php echo esc_html( $region['forecast'] . ' 권역 · ' . preg_replace( '/^\d{4}-(\d{2})-(\d{2})\s*/', '$1.$2 ', $fc['issued'] ) ); ?></span></h4>
					<div class="sw-kma__air-fcdays">
						<?php foreach ( $fc['days'] as $date => $d ) : ?>
							<?php
							$diff = (int) $today->diff( new DateTimeImmutable( $date, wp_timezone() ) )->format( '%r%a' );
							if ( $diff < 0 || $diff > 2 ) {
								continue;
							}
							?>
							<div class="sw-kma__air-fcday">
								<strong><?php echo esc_html( $labels[ $diff ] ); ?> <span class="sw-kma__muted"><?php echo esc_html( substr( $date, 5, 2 ) . '.' . substr( $date, 8, 2 ) ); ?></span></strong>
								<?php foreach ( array( 'PM10' => '미세먼지', 'PM25' => '초미세먼지' ) as $code => $name ) : ?>
									<?php
									$txt = $d[ $code ][ $region['forecast'] ] ?? '';
									$g   = (int) array_search( $txt, $grades, true );
									?>
									<span class="sw-kma__air-fcitem g<?php echo esc_attr( $g ); ?>"><?php echo esc_html( $name ); ?> <span class="sw-kma__air-badge"><?php echo esc_html( '' !== $txt ? $txt : '-' ); ?></span></span>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php elseif ( is_wp_error( $fc ) && current_user_can( 'manage_options' ) ) : ?>
				<?php self::render_error( $fc ); ?>
			<?php endif; ?>
		</section>
		<?php
		return $air;
	}

	/* ── 생활 예보 ─────────────────────────────────────── */

	/**
	 * 활동별 쾌적 기온 범위 [이름, 최저, 최고, 격한 운동 여부, 아이콘 path]
	 */
	private static function activities() {
		return array(
			'run'    => array( '달리기', 8, 18, true, '<circle cx="15" cy="4.5" r="2"/><path d="M13 8l-3 5h4l2 3v4M10 13l-4 1M13 8l3 3h3M11 16l-3 5"/>' ),
			'bike'   => array( '자전거 타기', 12, 24, true, '<circle cx="6" cy="17" r="4"/><circle cx="18" cy="17" r="4"/><path d="M6 17l4-8h6l2 8M10 9l4 8M8 6h3"/>' ),
			'hike'   => array( '하이킹하기', 10, 22, true, '<path d="M2 20l7-11 4 6 3-4 6 9z"/><path d="M7.5 11.5l1.5 1 1.5-1"/>' ),
			'garden' => array( '정원 가꾸기', 12, 25, false, '<path d="M12 20v-8M12 12c0-4-3-6-7-6 0 4 3 6 7 6zM12 14c0-3 2-5 6-5 0 3-2 5-6 5zM6 20h12"/>' ),
			'tennis' => array( '테니스 치기', 12, 25, true, '<circle cx="12" cy="12" r="8.5"/><path d="M5 6.5c3.5 2.5 3.5 8.5 0 11M19 6.5c-3.5 2.5-3.5 8.5 0 11"/>' ),
			'golf'   => array( '골프', 15, 25, false, '<path d="M9 20V3l8 3.5L9 10"/><ellipse cx="9" cy="20" rx="6" ry="1.5"/>' ),
			'camp'   => array( '캠핑하기', 15, 25, false, '<path d="M3 20h18M12 5L4 20M12 5l8 15M12 13l-3 7M12 13l3 7"/>' ),
		);
	}

	/**
	 * 생활 예보: 현재 시간대 평균 기온과 미세먼지·초미세먼지로 활동별 지수(1~5) 계산
	 */
	private static function render_life( array $hourly, $air ) {
		// 현재 시간대 평균 기온: 최근 3시간 관측값 평균
		$temps = array();
		for ( $i = count( $hourly ) - 1; $i >= 0 && count( $temps ) < 3; $i-- ) {
			if ( null !== $hourly[ $i ]['TA'] ) {
				$temps[] = $hourly[ $i ]['TA'];
				$last    = $last ?? $hourly[ $i ]['TM'];
			}
		}
		if ( ! $temps ) {
			return;
		}
		$ta   = array_sum( $temps ) / count( $temps );
		$hour = (int) substr( $last, 8, 2 );

		$pm10 = $air ? SW_Air_API::grade( 'pm10', $air['pm10'] ) : null;
		$pm25 = $air ? SW_Air_API::grade( 'pm25', $air['pm25'] ) : null;
		$dust = max( (int) $pm10, (int) $pm25 ); // 0 이면 대기질 자료 없음

		$levels = array( 5 => '아주 좋음', 4 => '좋음', 3 => '보통', 2 => '나쁨', 1 => '아주 나쁨' );
		$grades = SW_Air_API::GRADES;
		?>
		<section class="sw-kma__card sw-kma__life" aria-labelledby="sw-kma-life-title">
			<div class="sw-kma__card-head">
				<h3 id="sw-kma-life-title">생활 예보</h3>
				<span class="sw-kma__muted">
					<?php
					echo esc_html( sprintf( '%d시 기준 · 평균 기온 %.1f℃', $hour, $ta ) );
					if ( $pm10 ) {
						echo esc_html( ' · 미세먼지 ' . $grades[ $pm10 ] );
					}
					if ( $pm25 ) {
						echo esc_html( ' · 초미세먼지 ' . $grades[ $pm25 ] );
					}
					?>
				</span>
				<span class="sw-kma__life-nav">
					<button type="button" data-life-scroll="-1" aria-label="이전 활동">‹</button>
					<button type="button" data-life-scroll="1" aria-label="다음 활동">›</button>
				</span>
			</div>
			<ul class="sw-kma__life-list" tabindex="0" aria-label="활동별 생활 예보 (옆으로 밀어서 보기)">
				<?php foreach ( self::activities() as $key => $a ) : ?>
					<?php
					list( $name, $lo, $hi, $hard, $icon ) = $a;

					// 기온 점수: 쾌적 범위에서 벗어난 정도
					$off    = $ta < $lo ? $lo - $ta : ( $ta > $hi ? $ta - $hi : 0 );
					$t_lvl  = $off <= 0 ? 5 : ( $off <= 3 ? 4 : ( $off <= 6 ? 3 : ( $off <= 10 ? 2 : 1 ) ) );
					$t_note = $off <= 0 ? '' : ( $ta < $lo ? ( $off > 6 ? '추워요' : '쌀쌀해요' ) : ( $off > 6 ? '너무 더워요' : '더워요' ) );

					// 먼지 점수: 격한 운동일수록 엄격하게
					$d_map  = $hard ? array( 0 => 5, 1 => 5, 2 => 4, 3 => 2, 4 => 1 ) : array( 0 => 5, 1 => 5, 2 => 4, 3 => 3, 4 => 1 );
					$d_lvl  = $d_map[ $dust ];
					$d_note = $dust >= 2 ? ( $pm25 >= $pm10 ? '초미세먼지 ' : '미세먼지 ' ) . $grades[ max( $pm10, $pm25 ) ] : '';

					$lvl  = min( $t_lvl, $d_lvl );
					$note = 5 === $lvl ? '활동하기 딱 좋아요' : implode( ' · ', array_filter( array( $t_lvl < 5 ? $t_note : '', $d_lvl < 5 ? $d_note : '' ) ) );
					?>
					<li class="sw-kma__life-item l<?php echo esc_attr( $lvl ); ?>">
						<svg class="sw-kma__life-icon" viewBox="0 0 24 24" width="30" height="30" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput ?></svg>
						<strong><?php echo esc_html( $name ); ?></strong>
						<span class="sw-kma__life-level"><?php echo esc_html( $levels[ $lvl ] ); ?></span>
						<span class="sw-kma__life-dots" aria-hidden="true"><?php echo str_repeat( '<i class="on"></i>', $lvl ) . str_repeat( '<i></i>', 5 - $lvl ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<small><?php echo esc_html( $note ); ?></small>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	/* ── ③ 최근 7일 ──────────────────────────────────── */

	private static function render_daily( $stn ) {
		$days = SW_KMA_API::daily( $stn, 7 );
		if ( is_wp_error( $days ) || ! $days ) {
			if ( is_wp_error( $days ) && current_user_can( 'manage_options' ) ) {
				self::render_error( $days );
			}
			return;
		}

		$all  = array_filter( array_merge( array_column( $days, 'TA_MAX' ), array_column( $days, 'TA_MIN' ) ), 'is_numeric' );
		$lo   = $all ? floor( min( $all ) ) : 0;
		$hi   = $all ? ceil( max( $all ) ) : 1;
		$span = max( 1, $hi - $lo );
		$wd   = array( '일', '월', '화', '수', '목', '금', '토' );
		?>
		<section class="sw-kma__card" aria-labelledby="sw-kma-daily-title">
			<div class="sw-kma__card-head"><h3 id="sw-kma-daily-title">최근 7일</h3></div>
			<ol class="sw-kma__days">
				<?php foreach ( $days as $d ) : ?>
					<?php
					$dt   = DateTimeImmutable::createFromFormat( '!Ymd', substr( $d['TM'], 0, 8 ), wp_timezone() );
					$left = null === $d['TA_MIN'] ? 0 : ( $d['TA_MIN'] - $lo ) / $span * 100;
					$wide = ( null === $d['TA_MIN'] || null === $d['TA_MAX'] ) ? 0 : ( $d['TA_MAX'] - $d['TA_MIN'] ) / $span * 100;
					?>
					<li class="sw-kma__day">
						<span class="sw-kma__day-date"><b><?php echo esc_html( $dt ? $dt->format( 'n.j' ) : $d['TM'] ); ?></b> <?php echo esc_html( $dt ? $wd[ (int) $dt->format( 'w' ) ] : '' ); ?></span>
						<span class="sw-kma__day-min"><?php echo esc_html( self::fmt( $d['TA_MIN'] ) ); ?>°</span>
						<span class="sw-kma__day-bar" aria-hidden="true"><i style="left:<?php echo esc_attr( round( $left, 1 ) ); ?>%;width:<?php echo esc_attr( round( max( 2, $wide ), 1 ) ); ?>%"></i></span>
						<span class="sw-kma__day-max"><?php echo esc_html( self::fmt( $d['TA_MAX'] ) ); ?>°</span>
						<span class="sw-kma__day-rain<?php echo ! empty( $d['RN_DAY'] ) ? ' has-rain' : ''; ?>"><?php echo esc_html( ! empty( $d['RN_DAY'] ) ? self::fmt( $d['RN_DAY'], 1, 'mm' ) : '-' ); ?></span>
						<span class="sw-kma__day-extra">
							바람 <?php echo esc_html( self::fmt( $d['WS_AVG'], 1, 'm/s' ) ); ?>
							<?php if ( null !== $d['SS_DAY'] ) : ?> · 일조 <?php echo esc_html( self::fmt( $d['SS_DAY'], 1, 'h' ) ); ?><?php endif; ?>
							<?php if ( null !== $d['HM_AVG'] ) : ?> · 습도 <?php echo esc_html( self::fmt( $d['HM_AVG'], 0, '%' ) ); ?><?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
		<?php
	}

	/* ── ④ 해와 달 (한국천문연구원 출몰시각) ─────────────── */

	/**
	 * "HH:MM" → 분
	 */
	private static function minutes( $hm ) {
		return '' === $hm ? null : (int) substr( $hm, 0, 2 ) * 60 + (int) substr( $hm, 3, 2 );
	}

	/**
	 * 분 → "N시간 M분" / "M분"
	 */
	private static function span( $min ) {
		$h = intdiv( $min, 60 );
		$m = $min % 60;
		return ( $h ? $h . '시간 ' : '' ) . $m . '분';
	}

	private static function render_astro( $stn ) {
		$st     = SW_KMA_Data::station( $stn );
		$coords = SW_KMA_API::station_coords();
		$date   = wp_date( 'Ymd' );

		// 관측지점 좌표로 조회하고, 좌표가 없으면 지점 이름으로 지역 조회
		if ( isset( $coords[ $stn ] ) ) {
			$data  = SW_KSASI_API::get_coords( $coords[ $stn ]['lat'], $coords[ $stn ]['lon'], $date );
			$place = sprintf( '북위 %.2f° · 동경 %.2f°', $coords[ $stn ]['lat'], $coords[ $stn ]['lon'] );
		} else {
			$data  = SW_KSASI_API::get_area( $st['name'], $date );
			$place = $st['name'];
		}
		if ( is_wp_error( $data ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				self::render_error( $data );
			}
			return;
		}

		$t = array();
		foreach ( array( 'sunrise', 'suntransit', 'sunset', 'moonrise', 'moontransit', 'moonset', 'civilm', 'civile' ) as $k ) {
			$t[ $k ] = sw_ksasi_format_time( $data[ $k ] ?? '' );
		}
		$show = function ( $v ) {
			return '' === $v ? '—' : $v;
		};

		// 현재 해의 상태와 궤적 위치
		$now    = (int) wp_date( 'G' ) * 60 + (int) wp_date( 'i' );
		$rise   = self::minutes( $t['sunrise'] );
		$set    = self::minutes( $t['sunset'] );
		$status = '';
		$prog   = null;
		if ( null !== $rise && null !== $set ) {
			if ( $now < $rise ) {
				$status = '해 뜨기까지 ' . self::span( $rise - $now );
			} elseif ( $now < $set ) {
				$status = '해 지기까지 ' . self::span( $set - $now );
				$prog   = ( $now - $rise ) / ( $set - $rise );
			} else {
				$status = '해가 졌습니다';
			}
		}

		// 달 위상
		$moon     = sw_ksasi_calc_moon( $date );
		$api_age  = SW_KSASI_API::get_lunar_age( $date );
		$age      = null !== $api_age ? $api_age : $moon['age'];
		$name     = sw_ksasi_phase_name( $age );
		$illum    = (int) round( $moon['illum'] * 100 );
		$day_len  = sw_ksasi_duration( $t['sunrise'], $t['sunset'] );
		?>
		<section class="sw-kma__card sw-kma__astro" aria-labelledby="sw-kma-astro-title">
			<div class="sw-kma__card-head">
				<h3 id="sw-kma-astro-title">일출·일몰 · 월출·월몰</h3>
				<span class="sw-kma__muted"><?php echo esc_html( $st['name'] . ' 관측지점(' . $place . ') 기준 · 오늘' ); ?></span>
			</div>
			<div class="sw-kma__astro-grid">
				<div class="sw-kma__astro-panel is-sun">
					<div class="sw-kma__astro-visual">
						<svg class="sw-kma__sunarc" viewBox="0 0 200 104" aria-hidden="true">
							<path class="track" d="M10 95 A90 90 0 0 1 190 95" />
							<?php
							if ( null !== $prog ) :
								$ang = M_PI * ( 1 - $prog );
								$cx  = round( 100 + 90 * cos( $ang ), 2 );
								$cy  = round( 95 - 90 * sin( $ang ), 2 );
								?>
								<path class="done" d="M10 95 A90 90 0 0 1 <?php echo esc_attr( $cx . ' ' . $cy ); ?>" />
								<circle class="sun" cx="<?php echo esc_attr( $cx ); ?>" cy="<?php echo esc_attr( $cy ); ?>" r="8" />
							<?php endif; ?>
							<line class="horizon" x1="0" y1="95" x2="200" y2="95" />
						</svg>
						<?php if ( $status ) : ?><p class="sw-kma__astro-status"><?php echo esc_html( $status ); ?></p><?php endif; ?>
					</div>
					<dl class="sw-kma__astro-times">
						<div class="is-key"><dt>일출</dt><dd><?php echo esc_html( $show( $t['sunrise'] ) ); ?></dd></div>
						<div><dt>남중</dt><dd><?php echo esc_html( $show( $t['suntransit'] ) ); ?></dd></div>
						<div class="is-key"><dt>일몰</dt><dd><?php echo esc_html( $show( $t['sunset'] ) ); ?></dd></div>
					</dl>
					<p class="sw-kma__astro-foot">
						<?php if ( $day_len ) : ?>낮의 길이 <b><?php echo esc_html( $day_len ); ?></b><?php endif; ?>
						<?php if ( $t['civilm'] && $t['civile'] ) : ?> · 시민박명 <b><?php echo esc_html( $t['civilm'] . '~' . $t['civile'] ); ?></b><?php endif; ?>
					</p>
				</div>

				<div class="sw-kma__astro-panel is-moon">
					<div class="sw-kma__astro-visual sw-kma__astro-moon">
						<?php echo sw_ksasi_moon_svg( $moon['illum'], $moon['waxing'], sprintf( '%s, 밝기 %d%%', $name, $illum ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<div>
							<strong><?php echo esc_html( $name ); ?></strong>
							<span>월령 <?php echo esc_html( number_format( $age, 1 ) ); ?>일 · 밝기 <?php echo esc_html( $illum ); ?>%</span>
						</div>
					</div>
					<dl class="sw-kma__astro-times">
						<div class="is-key"><dt>월출</dt><dd><?php echo esc_html( $show( $t['moonrise'] ) ); ?></dd></div>
						<div><dt>남중</dt><dd><?php echo esc_html( $show( $t['moontransit'] ) ); ?></dd></div>
						<div class="is-key"><dt>월몰</dt><dd><?php echo esc_html( $show( $t['moonset'] ) ); ?></dd></div>
					</dl>
					<?php if ( '' === $t['moonrise'] || '' === $t['moonset'] ) : ?>
						<p class="sw-kma__astro-foot">‘—’는 오늘 그 현상이 없음을 뜻합니다. (달은 하루에 약 50분씩 늦게 떠서 월출·월몰이 없는 날이 있습니다)</p>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php
	}

	/* ── ⑤ 전국 관측 현황 ─────────────────────────────── */

	private static function render_nation( $current_stn ) {
		$all = SW_KMA_API::all_latest();
		if ( is_wp_error( $all ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				self::render_error( $all );
			}
			return;
		}
		$any = reset( $all );
		?>
		<section class="sw-kma__card" aria-labelledby="sw-kma-nation-title">
			<div class="sw-kma__card-head">
				<h3 id="sw-kma-nation-title">전국 관측 현황</h3>
				<span class="sw-kma__muted"><?php echo esc_html( SW_KMA_Data::format_tm( $any['TM'] ) ); ?> 기준 · 제목을 누르면 정렬</span>
			</div>
			<div class="sw-kma__table-wrap">
				<table class="sw-kma__table" data-sortable>
					<thead>
						<tr>
							<th data-sort="text">지역</th>
							<th data-sort="text">지점</th>
							<th>날씨</th>
							<th data-sort="num">기온</th>
							<th data-sort="num">습도</th>
							<th data-sort="num">풍속</th>
							<th data-sort="num">일강수</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( SW_KMA_Data::stations() as $id => $st ) : ?>
							<?php
							if ( ! isset( $all[ $id ] ) ) {
								continue;
							}
							$r  = $all[ $id ];
							$wx = SW_KMA_Data::weather( $r, self::is_night( $r['TM'] ) );
							?>
							<tr<?php echo $id === $current_stn ? ' class="is-current"' : ''; ?>>
								<td><?php echo esc_html( $st['region'] ); ?></td>
								<td><a href="<?php echo esc_url( self::station_url( $id ) ); ?>"><?php echo esc_html( $st['name'] ); ?></a></td>
								<td class="wx" title="<?php echo esc_attr( $wx['label'] ); ?>"><span class="wx-in"><?php echo SW_KMA_Data::icon( $wx['icon'], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="wx-label"><?php echo esc_html( '' !== $wx['label'] ? $wx['label'] : '-' ); ?></span></span></td>
								<td data-v="<?php echo esc_attr( (string) $r['TA'] ); ?>" class="num t"><?php echo esc_html( self::fmt( $r['TA'], 1, '℃' ) ); ?></td>
								<td data-v="<?php echo esc_attr( (string) $r['HM'] ); ?>" class="num"><?php echo esc_html( self::fmt( $r['HM'], 0, '%' ) ); ?></td>
								<td data-v="<?php echo esc_attr( (string) $r['WS'] ); ?>" class="num"><?php echo esc_html( self::fmt( $r['WS'], 1 ) ); ?></td>
								<td data-v="<?php echo esc_attr( (string) ( $r['RN_DAY'] ?? 0 ) ); ?>" class="num"><?php echo esc_html( null === $r['RN_DAY'] ? '-' : self::fmt( $r['RN_DAY'], 1 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php
	}
}
