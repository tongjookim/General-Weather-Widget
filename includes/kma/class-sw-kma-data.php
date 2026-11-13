<?php
/**
 * 기상청 지상관측(ASOS) 지점 목록과 코드 해석 도우미
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SW_KMA_Data {

	/**
	 * 종관기상관측(ASOS) 지점 [지점번호 => [이름, 권역]]
	 */
	public static function stations() {
		static $list = null;
		if ( null !== $list ) {
			return $list;
		}
		$groups = array(
			'서울·경기' => array( 108 => '서울', 112 => '인천', 119 => '수원', 98 => '동두천', 99 => '파주', 201 => '강화', 202 => '양평', 203 => '이천', 102 => '백령도' ),
			'강원'    => array( 101 => '춘천', 93 => '북춘천', 105 => '강릉', 104 => '북강릉', 90 => '속초', 106 => '동해', 114 => '원주', 100 => '대관령', 95 => '철원', 121 => '영월', 211 => '인제', 212 => '홍천', 216 => '태백', 217 => '정선군' ),
			'충청'    => array( 133 => '대전', 131 => '청주', 181 => '서청주', 239 => '세종', 232 => '천안', 127 => '충주', 129 => '서산', 177 => '홍성', 135 => '추풍령', 221 => '제천', 226 => '보은', 235 => '보령', 236 => '부여', 238 => '금산' ),
			'전라'    => array( 156 => '광주', 146 => '전주', 140 => '군산', 165 => '목포', 168 => '여수', 174 => '순천', 169 => '흑산도', 170 => '완도', 172 => '고창', 243 => '부안', 244 => '임실', 245 => '정읍', 247 => '남원', 248 => '장수', 251 => '고창군', 252 => '영광군', 254 => '순창군', 258 => '보성군', 259 => '강진군', 260 => '장흥', 261 => '해남', 262 => '고흥', 266 => '광양시', 268 => '진도군' ),
			'경상'    => array( 159 => '부산', 296 => '북부산', 143 => '대구', 152 => '울산', 155 => '창원', 255 => '북창원', 138 => '포항', 136 => '안동', 192 => '진주', 162 => '통영', 115 => '울릉도', 130 => '울진', 137 => '상주', 253 => '김해시', 257 => '양산시', 263 => '의령군', 264 => '함양군', 271 => '봉화', 272 => '영주', 273 => '문경', 276 => '청송군', 277 => '영덕', 278 => '의성', 279 => '구미', 281 => '영천', 283 => '경주시', 284 => '거창', 285 => '합천', 288 => '밀양', 289 => '산청', 294 => '거제', 295 => '남해' ),
			'제주'    => array( 184 => '제주', 189 => '서귀포', 185 => '고산', 188 => '성산' ),
		);
		$list = array();
		foreach ( $groups as $region => $items ) {
			foreach ( $items as $id => $name ) {
				$list[ $id ] = array( 'name' => $name, 'region' => $region );
			}
		}
		return $list;
	}

	public static function station( $id ) {
		$list = self::stations();
		return isset( $list[ (int) $id ] ) ? $list[ (int) $id ] : null;
	}

	/**
	 * ASOS 지점 → 에어코리아 권역
	 *
	 * forecast: 대기질 예보 19개 권역명, sido: 실시간 측정자료의 시도명(sidoName).
	 * 실시간 자료에서 광주·전남은 '전남광주'로 묶여 온다.
	 *
	 * @return array|null ['forecast' => '경기남부', 'sido' => '경기']
	 */
	public static function air_region( $id ) {
		static $map = null;
		if ( null === $map ) {
			$groups = array(
				'서울'   => array( 108 ),
				'인천'   => array( 112, 201, 102 ),
				'경기북부' => array( 98, 99 ),
				'경기남부' => array( 119, 202, 203 ),
				'영서'   => array( 101, 93, 114, 100, 95, 121, 211, 212, 217 ),
				'영동'   => array( 105, 104, 90, 106, 216 ),
				'대전'   => array( 133 ),
				'세종'   => array( 239 ),
				'충북'   => array( 131, 181, 127, 135, 221, 226 ),
				'충남'   => array( 232, 129, 177, 235, 236, 238 ),
				'광주'   => array( 156 ),
				'전북'   => array( 146, 140, 172, 243, 244, 245, 247, 248, 251, 254 ),
				'전남'   => array( 165, 168, 174, 169, 170, 252, 258, 259, 260, 261, 262, 266, 268 ),
				'부산'   => array( 159, 296 ),
				'대구'   => array( 143 ),
				'울산'   => array( 152 ),
				'경북'   => array( 138, 136, 115, 130, 137, 271, 272, 273, 276, 277, 278, 279, 281, 283 ),
				'경남'   => array( 155, 255, 192, 162, 253, 257, 263, 264, 284, 285, 288, 289, 294, 295 ),
				'제주'   => array( 184, 189, 185, 188 ),
			);
			$sido = array( '경기북부' => '경기', '경기남부' => '경기', '영서' => '강원', '영동' => '강원', '광주' => '전남광주', '전남' => '전남광주' );
			$map  = array();
			foreach ( $groups as $region => $ids ) {
				foreach ( $ids as $stn ) {
					$map[ $stn ] = array( 'forecast' => $region, 'sido' => $sido[ $region ] ?? $region );
				}
			}
		}
		return $map[ (int) $id ] ?? null;
	}

	/**
	 * 풍향(도) → 16방위 한글
	 */
	public static function wind_dir( $deg ) {
		if ( null === $deg ) {
			return '';
		}
		$names = array( '북', '북북동', '북동', '동북동', '동', '동남동', '남동', '남남동', '남', '남남서', '남서', '서남서', '서', '서북서', '북서', '북북서' );
		return $names[ (int) round( fmod( $deg, 360 ) / 22.5 ) % 16 ];
	}

	/**
	 * API 풍향 값 → 도(°). 시간자료 풍향은 36방위(10° 단위)로 제공된다.
	 */
	public static function wd_to_deg( $wd ) {
		if ( null === $wd ) {
			return null;
		}
		return $wd <= 36 ? $wd * 10 : $wd;
	}

	/**
	 * YYYYMMDDHHMI → "11.13 09:00" / YYYYMMDD → "11.13"
	 */
	public static function format_tm( $tm, $with_year = false ) {
		$tm = (string) $tm;
		if ( strlen( $tm ) < 8 ) {
			return $tm;
		}
		$out = ( $with_year ? substr( $tm, 0, 4 ) . '.' : '' ) . substr( $tm, 4, 2 ) . '.' . substr( $tm, 6, 2 );
		if ( strlen( $tm ) >= 12 ) {
			$out .= ' ' . substr( $tm, 8, 2 ) . ':' . substr( $tm, 10, 2 );
		}
		return $out;
	}

	/**
	 * 관측값으로 날씨 상태 판단 → [label, icon]
	 * icon: sun, partly, cloud, rain, snow, sleet, shower, thunder, fog, haze, dust
	 *
	 * @param array $row 시간자료 한 행.
	 * @param bool  $night 밤 여부(아이콘용).
	 */
	public static function weather( array $row, $night = false ) {
		$wc = isset( $row['WC'] ) ? $row['WC'] : null;
		if ( null !== $wc ) {
			$wc = (int) $wc;
			$map = array(
				array( 95, 99, '뇌우', 'thunder' ),
				array( 91, 94, '뇌우 후 비', 'rain' ),
				array( 87, 90, '우박', 'sleet' ),
				array( 85, 86, '소낙눈', 'snow' ),
				array( 83, 84, '소낙성 진눈깨비', 'sleet' ),
				array( 80, 82, '소나기', 'shower' ),
				array( 70, 79, '눈', 'snow' ),
				array( 68, 69, '진눈깨비', 'sleet' ),
				array( 66, 67, '어는 비', 'sleet' ),
				array( 60, 65, '비', 'rain' ),
				array( 50, 59, '이슬비', 'rain' ),
				array( 40, 49, '안개', 'fog' ),
				array( 36, 39, '날린눈', 'snow' ),
				array( 30, 35, '황사', 'dust' ),
				array( 17, 17, '뇌전', 'thunder' ),
				array( 13, 13, '번개', 'thunder' ),
				array( 11, 12, '안개', 'fog' ),
				array( 10, 10, '박무', 'haze' ),
				array( 6, 9, '황사', 'dust' ),
				array( 4, 5, '연무', 'haze' ),
			);
			foreach ( $map as $m ) {
				if ( $wc >= $m[0] && $wc <= $m[1] ) {
					return array( 'label' => $m[2], 'icon' => $m[3] );
				}
			}
		}

		// 일기 관측이 없으면(무인 관측소) 강수 → 운량 순으로 판단
		if ( ! empty( $row['RN'] ) && $row['RN'] > 0 ) {
			return null !== $row['TA'] && $row['TA'] <= 0
				? array( 'label' => '눈', 'icon' => 'snow' )
				: array( 'label' => '비', 'icon' => 'rain' );
		}
		if ( isset( $row['CA_TOT'] ) && null !== $row['CA_TOT'] ) {
			if ( $row['CA_TOT'] <= 5 ) {
				return array( 'label' => '맑음', 'icon' => $night ? 'moon' : 'sun' );
			}
			if ( $row['CA_TOT'] <= 8 ) {
				return array( 'label' => '구름많음', 'icon' => 'partly' );
			}
			return array( 'label' => '흐림', 'icon' => 'cloud' );
		}
		return array( 'label' => '', 'icon' => '' );
	}

	/**
	 * 날씨 아이콘 SVG (currentColor 기반)
	 */
	public static function icon( $name, $size = 48 ) {
		$sun    = '<circle cx="24" cy="24" r="8" fill="#f59e0b"/><g stroke="#f59e0b" stroke-width="3" stroke-linecap="round"><path d="M24 6v4M24 38v4M6 24h4M38 24h4M11.3 11.3l2.8 2.8M33.9 33.9l2.8 2.8M11.3 36.7l2.8-2.8M33.9 14.1l2.8-2.8"/></g>';
		$cloud  = '<path d="M15 36h20a8 8 0 0 0 .9-15.95A11 11 0 0 0 14.6 23 6.5 6.5 0 0 0 15 36z" fill="#cbd5e1" stroke="#94a3b8" stroke-width="1.5"/>';
		$drops  = '<g stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round"><path d="M18 40l-2 5M25 40l-2 5M32 40l-2 5"/></g>';
		$flakes = '<g fill="#60a5fa"><circle cx="17" cy="42" r="2"/><circle cx="24" cy="45" r="2"/><circle cx="31" cy="42" r="2"/></g>';
		$shapes = array(
			'sun'     => $sun,
			'moon'    => '<path d="M30 8a16 16 0 1 0 10 28A13 13 0 0 1 30 8z" fill="#facc15"/>',
			'partly'  => '<g transform="translate(-6 -6) scale(.8)">' . $sun . '</g>' . $cloud,
			'cloud'   => $cloud,
			'rain'    => '<g transform="translate(0 -4)">' . $cloud . '</g>' . $drops,
			'shower'  => '<g transform="translate(-6 -8) scale(.75)">' . $sun . '</g><g transform="translate(0 -4)">' . $cloud . '</g>' . $drops,
			'snow'    => '<g transform="translate(0 -4)">' . $cloud . '</g>' . $flakes,
			'sleet'   => '<g transform="translate(0 -4)">' . $cloud . '</g><g stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round"><path d="M18 40l-2 5M30 40l-2 5"/></g><circle cx="24" cy="43" r="2" fill="#60a5fa"/>',
			'thunder' => '<g transform="translate(0 -4)">' . $cloud . '</g><path d="M25 34l-5 8h5l-3 6 8-10h-5l3-4z" fill="#f59e0b"/>',
			'fog'     => '<g stroke="#94a3b8" stroke-width="3" stroke-linecap="round"><path d="M10 18h28M6 26h36M10 34h28"/></g>',
			'haze'    => '<g transform="translate(0 -6) scale(.8) translate(6 0)">' . $sun . '</g><g stroke="#94a3b8" stroke-width="3" stroke-linecap="round"><path d="M8 36h32M12 42h24"/></g>',
			'dust'    => '<g stroke="#b45309" stroke-width="3" stroke-linecap="round"><path d="M8 16h22M14 24h28M6 32h26M16 40h20"/></g>',
		);
		if ( ! isset( $shapes[ $name ] ) ) {
			return '';
		}
		return sprintf( '<svg class="sw-kma-icon" width="%1$d" height="%1$d" viewBox="0 0 48 48" aria-hidden="true">%2$s</svg>', (int) $size, $shapes[ $name ] );
	}
}
