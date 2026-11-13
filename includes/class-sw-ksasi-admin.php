<?php
/**
 * 관리자 설정 페이지 (설정 > KASI 출몰시각)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SW_KSASI_Admin {

	const PAGE = 'sw-ksasi-sync';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_sw_ksasi_flush', array( __CLASS__, 'flush' ) );
		add_action( 'admin_post_sw_kma_create_page', array( __CLASS__, 'create_page' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SW_KSASI_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_options_page( 'KASI·기상청 설정', 'KASI·기상청', 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">설정</a>' );
		return $links;
	}

	public static function register() {
		register_setting( self::PAGE, SW_KSASI_OPTION, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => sw_ksasi_default_settings(),
		) );
	}

	public static function sanitize( $input ) {
		$input = (array) $input;
		$old   = sw_ksasi_get_setting();
		$new   = array(
			'service_key' => isset( $input['service_key'] ) ? self::sanitize_key_value( $input['service_key'] ) : '',
			'location'    => isset( $input['location'] ) ? sanitize_text_field( $input['location'] ) : '서울',
			'cache_hours' => isset( $input['cache_hours'] ) ? min( 168, max( 1, absint( $input['cache_hours'] ) ) ) : 12,
			// 기상청 API허브 인증키 (영문·숫자·-·_ 로 구성)
			'kma_auth_key' => isset( $input['kma_auth_key'] ) ? preg_replace( '#[^A-Za-z0-9_-]#', '', (string) $input['kma_auth_key'] ) : '',
			'kma_stn'      => isset( $input['kma_stn'] ) && SW_KMA_Data::station( absint( $input['kma_stn'] ) ) ? absint( $input['kma_stn'] ) : 108,
			'kma_page_id'  => isset( $input['kma_page_id'] ) ? absint( $input['kma_page_id'] ) : (int) $old['kma_page_id'],
		);
		if ( '' === $new['location'] ) {
			$new['location'] = '서울';
		}
		// 키가 바뀌면 이전 키로 저장된 캐시를 비운다.
		if ( $old['service_key'] !== $new['service_key'] || $old['kma_auth_key'] !== $new['kma_auth_key'] ) {
			sw_ksasi_flush_cache();
		}
		return $new;
	}

	/**
	 * 서비스 키 정리.
	 * sanitize_text_field() 는 "%2B", "%3D" 같은 퍼센트 인코딩을 삭제하므로 인코딩 키가 잘린다.
	 * 인코딩 키는 디코딩해 원본(+, /, =)으로 저장하고, 키에 쓰이는 문자만 남긴다.
	 */
	private static function sanitize_key_value( $key ) {
		$key = trim( wp_unslash( (string) $key ) );
		if ( false !== strpos( $key, '%' ) ) {
			$key = rawurldecode( $key );
		}
		return preg_replace( '#[^A-Za-z0-9+/=_-]#', '', $key );
	}

	/**
	 * [sw_kma_weather] 숏코드가 들어간 '날씨' 페이지 생성 (슬러그 weather)
	 */
	public static function create_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '권한이 없습니다.' );
		}
		check_admin_referer( 'sw_kma_create_page' );
		$id = SW_KMA_Page::create_page();
		wp_safe_redirect( is_wp_error( $id ) ? admin_url( 'options-general.php?page=' . self::PAGE ) : get_edit_post_link( $id, 'url' ) );
		exit;
	}

	public static function flush() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '권한이 없습니다.' );
		}
		check_admin_referer( 'sw_ksasi_flush' );
		sw_ksasi_flush_cache();
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE . '&flushed=1' ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = sw_ksasi_get_setting();

		// 연결 테스트
		$test = null;
		if ( '' !== $s['service_key'] ) {
			$test = SW_KSASI_API::get_area( $s['location'], wp_date( 'Ymd' ) );
		}
		?>
		<div class="wrap">
			<h1>KASI 출몰시각 · 기상청 지상관측 설정</h1>

			<?php if ( isset( $_GET['flushed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p>캐시를 삭제했습니다.</p></div>
			<?php endif; ?>

			<?php if ( is_wp_error( $test ) ) : ?>
				<div class="notice notice-error"><p><strong>연결 테스트 실패:</strong> <?php echo esc_html( $test->get_error_message() ); ?></p></div>
			<?php elseif ( is_array( $test ) ) : ?>
				<div class="notice notice-success"><p><strong>연결 정상</strong> — 오늘 <?php echo esc_html( $s['location'] ); ?> 일출 <?php echo esc_html( sw_ksasi_format_time( $test['sunrise'] ?? '' ) ); ?>, 일몰 <?php echo esc_html( sw_ksasi_format_time( $test['sunset'] ?? '' ) ); ?></p></div>
			<?php endif; ?>

			<?php
			// 기상청 API허브 연결 테스트
			if ( '' !== $s['kma_auth_key'] ) :
				$kma_test = SW_KMA_API::latest( (int) $s['kma_stn'] );
				$kma_stn  = SW_KMA_Data::station( (int) $s['kma_stn'] );
				?>
				<?php if ( is_wp_error( $kma_test ) ) : ?>
					<div class="notice notice-error"><p><strong>기상청 API허브 연결 실패:</strong> <?php echo esc_html( $kma_test->get_error_message() ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-success"><p><strong>기상청 API허브 연결 정상</strong> — <?php echo esc_html( $kma_stn['name'] ?? '' ); ?> <?php echo esc_html( SW_KMA_Data::format_tm( $kma_test['TM'] ) ); ?> 기온 <?php echo esc_html( null === $kma_test['TA'] ? '-' : $kma_test['TA'] . '℃' ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<?php
			// 에어코리아 연결 테스트 (공공데이터포털 서비스 키 공용)
			if ( '' !== $s['service_key'] ) :
				$air_test   = SW_Air_API::realtime();
				$air_coords = is_wp_error( $air_test ) ? array() : SW_Air_API::station_coords();
				?>
				<?php if ( is_wp_error( $air_test ) ) : ?>
					<div class="notice notice-warning"><p><strong>에어코리아 대기오염정보 연결 실패:</strong> <?php echo esc_html( $air_test->get_error_message() ); ?> (날씨 페이지의 대기질 섹션이 표시되지 않습니다)</p></div>
				<?php else : ?>
					<div class="notice notice-success"><p><strong>에어코리아 연결 정상</strong> — 측정소 <?php echo esc_html( count( $air_test ) ); ?>곳, <?php echo esc_html( $air_test[0]['time'] ); ?> 기준.
						<?php echo $air_coords ? esc_html( '측정소정보 사용 중: 관측지점에서 가장 가까운 측정소 기준으로 표시합니다.' ) : wp_kses( '측정소정보 API 미신청: 시도 평균으로 표시합니다. <b>한국환경공단_에어코리아_측정소정보</b>를 활용신청하면 가까운 측정소 기준으로 바뀝니다.', array( 'b' => array() ) ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<?php $kma_page =(int) $s['kma_page_id'] ? get_post( (int) $s['kma_page_id'] ) : null; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sw_ksasi_key">서비스 키 (ServiceKey)</label></th>
						<td>
							<input type="text" id="sw_ksasi_key" class="large-text code" name="<?php echo esc_attr( SW_KSASI_OPTION ); ?>[service_key]" value="<?php echo esc_attr( $s['service_key'] ); ?>" autocomplete="off">
							<p class="description">공공데이터포털(data.go.kr)에서 <strong>한국천문연구원_출몰시각 정보</strong> 활용신청 후 발급받은 키. 인코딩/디코딩 키 모두 사용 가능합니다.<br>날씨 페이지의 대기질도 이 키를 씁니다: <strong>한국환경공단_에어코리아_대기오염정보</strong>(필수), <strong>한국환경공단_에어코리아_측정소정보</strong>(선택)를 같은 계정에서 활용신청하세요.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sw_ksasi_loc">기본 지역</label></th>
						<td>
							<input type="text" id="sw_ksasi_loc" list="sw_ksasi_locations" name="<?php echo esc_attr( SW_KSASI_OPTION ); ?>[location]" value="<?php echo esc_attr( $s['location'] ); ?>">
							<datalist id="sw_ksasi_locations">
								<?php foreach ( sw_ksasi_locations() as $loc ) : ?>
									<option value="<?php echo esc_attr( $loc ); ?>">
								<?php endforeach; ?>
							</datalist>
							<p class="description">숏코드에 location 속성이 없을 때 사용합니다.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sw_ksasi_cache">캐시 시간</label></th>
						<td>
							<input type="number" id="sw_ksasi_cache" min="1" max="168" class="small-text" name="<?php echo esc_attr( SW_KSASI_OPTION ); ?>[cache_hours]" value="<?php echo esc_attr( $s['cache_hours'] ); ?>"> 시간
							<p class="description">출몰시각 데이터는 연 1회 갱신되므로 길게 설정해도 무방합니다.</p>
						</td>
					</tr>
				</table>

				<h2>기상청 API허브 (지상관측 페이지)</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sw_kma_key">인증키 (authKey)</label></th>
						<td>
							<input type="text" id="sw_kma_key" class="large-text code" name="<?php echo esc_attr( SW_KSASI_OPTION ); ?>[kma_auth_key]" value="<?php echo esc_attr( $s['kma_auth_key'] ); ?>" autocomplete="off">
							<p class="description"><a href="https://apihub.kma.go.kr/" target="_blank" rel="noopener">기상청 API허브</a> 회원가입 후 <strong>지상관측</strong> API 활용신청을 하고, 마이페이지에서 확인한 인증키. (공공데이터포털 키와 다릅니다)</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sw_kma_stn">기본 관측지점</label></th>
						<td>
							<select id="sw_kma_stn" name="<?php echo esc_attr( SW_KSASI_OPTION ); ?>[kma_stn]">
								<?php foreach ( SW_KMA_Data::stations() as $id => $st ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>" <?php selected( (int) $s['kma_stn'], $id ); ?>><?php echo esc_html( $st['name'] . ' (' . $id . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">날씨 페이지</th>
						<td>
							<?php if ( $kma_page && 'trash' !== $kma_page->post_status ) : ?>
								<a href="<?php echo esc_url( get_permalink( $kma_page ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $kma_page ) ); ?></a>
								(<?php echo esc_html( get_post_status_object( $kma_page->post_status )->label ); ?>)
								· <a href="<?php echo esc_url( get_edit_post_link( $kma_page ) ); ?>">편집</a>
							<?php else : ?>
								아직 없습니다. 아래 <strong>날씨 페이지 만들기</strong> 버튼을 누르거나, 원하는 페이지에 <code>[sw_kma_weather]</code> 숏코드를 넣으세요.
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sw_ksasi_flush">
				<?php wp_nonce_field( 'sw_ksasi_flush' ); ?>
				<?php submit_button( '캐시 비우기', 'secondary', 'submit', false ); ?>
			</form>

			<?php if ( ! $kma_page || 'trash' === $kma_page->post_status ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
					<input type="hidden" name="action" value="sw_kma_create_page">
					<?php wp_nonce_field( 'sw_kma_create_page' ); ?>
					<?php submit_button( '날씨 페이지 만들기', 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<hr>
			<h2>숏코드 사용법</h2>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>예시</th><th>설명</th></tr></thead>
				<tbody>
					<tr><td><code>[sw_ksasi_riseset]</code></td><td>기본 지역, 오늘 날짜의 일출/일몰·월출/월몰</td></tr>
					<tr><td><code>[sw_ksasi_riseset location="부산"]</code></td><td>지역명으로 조회</td></tr>
					<tr><td><code>[sw_ksasi_riseset lat="37.5665" lng="126.9780" label="서울시청"]</code></td><td>위경도(10진수)로 조회, label 은 표시 이름</td></tr>
					<tr><td><code>[sw_ksasi_riseset type="sun"]</code></td><td>해만 표시 (<code>sun</code> / <code>moon</code> / <code>both</code>)</td></tr>
					<tr><td><code>[sw_ksasi_riseset date="20261225"]</code></td><td>특정 날짜(YYYYMMDD) 조회</td></tr>
					<tr><td><code>[sw_ksasi_riseset twilight="yes"]</code></td><td>시민·항해·천문 박명 시각 추가 표시</td></tr>
					<tr><td><code>[sw_ksasi_riseset nav="no" title="오늘의 해와 달"]</code></td><td>날짜 이동 버튼 숨김, 제목 변경</td></tr>
					<tr><td><code>[sw_ksasi_riseset theme="dark"]</code></td><td>디자인 테마 (<code>light</code> / <code>dark</code>)</td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
