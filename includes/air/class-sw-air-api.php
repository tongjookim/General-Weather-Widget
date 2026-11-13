<?php
/**
 * 한국환경공단 에어코리아 대기오염정보 API 클라이언트 (공공데이터포털 JSON)
 *
 * 명세: airkorea.md
 * - 대기오염정보(ArpltnInforInqireSvc): 시도별 실시간 측정정보, 대기질 예보통보 — 필수
 * - 측정소정보(MsrstnInfoInqireSvc): 측정소 좌표 — 선택. 있으면 가장 가까운 측정소, 없으면 시도 평균
 *
 * 인증키: 에어코리아 서비스 키(air_service_key)를 먼저 쓰고, 비어 있거나 인증 오류면 KASI 서비스 키(service_key)로 다시 시도한다.
 * (API마다 다른 계정으로 활용신청한 경우를 위해)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SW_Air_API {

	const BASE_URL = 'http://apis.data.go.kr/B552584/';

	/** 오염물질: 응답 필드 접두사 => [표시명, 단위, 소수 자릿수, 등급 경계(좋음·보통·나쁨 상한)] */
	const ITEMS = array(
		'pm10' => array( '미세먼지', '㎍/㎥', 0, array( 30, 80, 150 ) ),
		'pm25' => array( '초미세먼지', '㎍/㎥', 0, array( 15, 35, 75 ) ),
		'o3'   => array( '오존', 'ppm', 3, array( 0.030, 0.090, 0.150 ) ),
		'no2'  => array( '이산화질소', 'ppm', 3, array( 0.030, 0.060, 0.200 ) ),
		'co'   => array( '일산화탄소', 'ppm', 1, array( 2, 9, 15 ) ),
		'so2'  => array( '아황산가스', 'ppm', 3, array( 0.020, 0.050, 0.150 ) ),
		'khai' => array( '통합대기환경지수', '', 0, array( 50, 100, 250 ) ),
	);

	const GRADES = array( 1 => '좋음', 2 => '보통', 3 => '나쁨', 4 => '매우나쁨' );

	/**
	 * 값 → 등급(1~4)
	 */
	public static function grade( $item, $v ) {
		if ( null === $v || ! isset( self::ITEMS[ $item ] ) ) {
			return null;
		}
		foreach ( self::ITEMS[ $item ][3] as $i => $max ) {
			if ( $v <= $max ) {
				return $i + 1;
			}
		}
		return 4;
	}

	/**
	 * 전국 측정소 실시간 측정값 (getCtprvnRltmMesureDnsty, sidoName=전국)
	 * 매시 15분 내외 갱신되므로 15분 캐시. 필요한 항목만 줄여서 저장한다.
	 *
	 * @return array|WP_Error 측정소 행 배열
	 */
	public static function realtime() {
		$key    = 'sw_ksasi_air_rltm';
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$items = self::request( 'ArpltnInforInqireSvc/getCtprvnRltmMesureDnsty', array(
			'sidoName'  => '전국',
			'ver'       => '1.3',
			'numOfRows' => 1000,
		) );
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		$out = array();
		foreach ( $items as $it ) {
			$row = array(
				'name' => (string) ( $it['stationName'] ?? '' ),
				'sido' => (string) ( $it['sidoName'] ?? '' ),
				'mang' => (string) ( $it['mangName'] ?? '' ),
				'time' => (string) ( $it['dataTime'] ?? '' ),
			);
			foreach ( array_keys( self::ITEMS ) as $k ) {
				$row[ $k ] = self::num( $it, $k . 'Value', $k . 'Flag' );
			}
			$row['pm10_24']    = self::num( $it, 'pm10Value24', 'pm10Flag' );
			$row['pm25_24']    = self::num( $it, 'pm25Value24', 'pm25Flag' );
			$row['khai_grade'] = isset( $it['khaiGrade'] ) && is_numeric( $it['khaiGrade'] ) ? (int) $it['khaiGrade'] : null;
			if ( '' !== $row['name'] ) {
				$out[] = $row;
			}
		}
		if ( ! $out ) {
			return new WP_Error( 'sw_air_empty', '에어코리아 실시간 측정자료가 없습니다.' );
		}
		set_transient( $key, $out, 15 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * 측정소 좌표 [측정소명 => [lat, lon, addr]] (getMsrstnList ver=1.1)
	 * 측정소정보 API 는 별도 활용신청이 필요하다. 실패하면 12시간 동안 다시 부르지 않고 빈 배열을 돌려준다.
	 *
	 * @return array
	 */
	public static function station_coords() {
		$key    = 'sw_ksasi_air_stations';
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( get_transient( 'sw_ksasi_air_stations_fail' ) ) {
			return array();
		}
		$items = self::request( 'MsrstnInfoInqireSvc/getMsrstnList', array(
			'ver'       => '1.1',
			'numOfRows' => 1000,
		) );
		$out = array();
		if ( ! is_wp_error( $items ) ) {
			foreach ( $items as $it ) {
				$a = isset( $it['dmX'] ) ? (float) $it['dmX'] : 0;
				$b = isset( $it['dmY'] ) ? (float) $it['dmY'] : 0;
				// 버전에 따라 X/Y 가 뒤바뀌어 오므로 값의 범위로 위도·경도를 가린다.
				$lat = $a < 90 ? $a : $b;
				$lon = $a < 90 ? $b : $a;
				if ( $lat > 30 && $lat < 40 && $lon > 120 && $lon < 135 && ! empty( $it['stationName'] ) ) {
					$out[ $it['stationName'] ] = array( 'lat' => $lat, 'lon' => $lon, 'addr' => (string) ( $it['addr'] ?? '' ) );
				}
			}
		}
		if ( ! $out ) {
			set_transient( 'sw_ksasi_air_stations_fail', 1, 12 * HOUR_IN_SECONDS );
			return array();
		}
		set_transient( $key, $out, 30 * DAY_IN_SECONDS );
		return $out;
	}

	/**
	 * 관측지점 기준 대기질
	 *
	 * 측정소 좌표가 있으면 가장 가까운 측정소(도로변 측정소 제외), 없으면 시도 도시대기 측정소 평균.
	 *
	 * @param float|null $lat  관측지점 위도.
	 * @param float|null $lon  관측지점 경도.
	 * @param string     $sido 실시간 자료의 시도명.
	 * @return array|WP_Error [mode, label, dist, count, time, 값...]
	 */
	public static function for_location( $lat, $lon, $sido ) {
		$rows = self::realtime();
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$coords = ( null !== $lat && null !== $lon ) ? self::station_coords() : array();
		if ( $coords ) {
			$best = null;
			$dist = INF;
			foreach ( $rows as $r ) {
				if ( '도로변대기' === $r['mang'] || ! isset( $coords[ $r['name'] ] ) || ( null === $r['pm10'] && null === $r['pm25'] ) ) {
					continue;
				}
				$d = self::distance( $lat, $lon, $coords[ $r['name'] ]['lat'], $coords[ $r['name'] ]['lon'] );
				if ( $d < $dist ) {
					$dist = $d;
					$best = $r;
				}
			}
			if ( $best ) {
				return $best + array(
					'mode'  => 'nearest',
					'label' => $best['name'],
					'dist'  => $dist,
					'addr'  => $coords[ $best['name'] ]['addr'],
					'count' => 1,
				);
			}
		}

		// 시도 평균 (도시대기 측정소)
		$pick = array_filter( $rows, function ( $r ) use ( $sido ) {
			return $r['sido'] === $sido && '도시대기' === $r['mang'];
		} );
		if ( ! $pick ) {
			return new WP_Error( 'sw_air_empty', sprintf( '%s 지역 대기질 측정자료가 없습니다.', $sido ) );
		}
		$out = array(
			'mode'       => 'sido',
			'label'      => $sido,
			'dist'       => null,
			'addr'       => '',
			'count'      => count( $pick ),
			'time'       => max( array_column( $pick, 'time' ) ),
			'khai_grade' => null,
		);
		foreach ( array_merge( array_keys( self::ITEMS ), array( 'pm10_24', 'pm25_24' ) ) as $k ) {
			$vals      = array_filter( array_column( $pick, $k ), 'is_numeric' );
			$out[ $k ] = $vals ? array_sum( $vals ) / count( $vals ) : null;
		}
		return $out;
	}

	/**
	 * 대기질 예보통보 (getMinuDustFrcstDspth) — 가장 최근 발표분
	 *
	 * 하루 4회(05·11·17·23시) 발표. 오늘 발표가 아직 없으면(자정~05시) 어제 발표분을 쓴다.
	 *
	 * @return array|WP_Error ['issued' => '...', 'days' => [Y-m-d => ['PM10' => [권역 => 등급], 'PM25' => [...], 'overall' => '...']]]
	 */
	public static function forecast() {
		$now    = new DateTimeImmutable( 'now', wp_timezone() );
		$key    = 'sw_ksasi_air_frcst_' . $now->format( 'YmdH' );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$items = array();
		foreach ( array( $now, $now->modify( '-1 day' ) ) as $d ) {
			$items = self::request( 'ArpltnInforInqireSvc/getMinuDustFrcstDspth', array(
				'searchDate' => $d->format( 'Y-m-d' ),
				'numOfRows'  => 100,
			) );
			if ( is_wp_error( $items ) ) {
				return $items;
			}
			if ( $items ) {
				break;
			}
		}
		if ( ! $items ) {
			return new WP_Error( 'sw_air_empty', '대기질 예보가 아직 발표되지 않았습니다.' );
		}

		// 발표 시각("2026-11-13 11시 발표")이 가장 늦은 묶음만 사용
		$issued = max( array_column( $items, 'dataTime' ) );
		$today  = $now->format( 'Y-m-d' );
		$days   = array();
		foreach ( $items as $it ) {
			$code = $it['informCode'] ?? '';
			$date = $it['informData'] ?? '';
			if ( $issued !== $it['dataTime'] || ! in_array( $code, array( 'PM10', 'PM25' ), true ) || $date < $today ) {
				continue;
			}
			$grades = array();
			foreach ( explode( ',', (string) ( $it['informGrade'] ?? '' ) ) as $pair ) {
				$p = array_map( 'trim', explode( ':', $pair ) );
				if ( 2 === count( $p ) && '' !== $p[0] ) {
					$grades[ $p[0] ] = $p[1];
				}
			}
			$days[ $date ][ $code ] = $grades;
			if ( 'PM10' === $code || empty( $days[ $date ]['overall'] ) ) {
				$days[ $date ]['overall'] = trim( preg_replace( '/^[○\s]+/u', '', (string) ( $it['informOverall'] ?? '' ) ) );
			}
		}
		ksort( $days );
		$out = array( 'issued' => $issued, 'days' => $days );
		set_transient( $key, $out, HOUR_IN_SECONDS );
		return $out;
	}

	/* ── 내부 ─────────────────────────────────────────── */

	/**
	 * 숫자 값 (점검·통신장애 등 flag 가 있거나 "-" 이면 null)
	 */
	private static function num( array $it, $field, $flag ) {
		if ( ! empty( $it[ $flag ] ) || ! isset( $it[ $field ] ) || ! is_numeric( $it[ $field ] ) ) {
			return null;
		}
		$v = (float) $it[ $field ];
		return $v < 0 ? null : $v;
	}

	/**
	 * 두 좌표 사이 거리(km)
	 */
	public static function distance( $lat1, $lon1, $lat2, $lon2 ) {
		$r  = 6371;
		$p1 = deg2rad( $lat1 );
		$p2 = deg2rad( $lat2 );
		$a  = sin( ( $p2 - $p1 ) / 2 ) ** 2 + cos( $p1 ) * cos( $p2 ) * sin( deg2rad( $lon2 - $lon1 ) / 2 ) ** 2;
		return 2 * $r * asin( min( 1, sqrt( $a ) ) );
	}

	/**
	 * 공통 요청 (JSON)
	 *
	 * @return array|WP_Error items 배열
	 */
	private static function request( $operation, array $params ) {
		$keys = array_values( array_unique( array_filter( array(
			trim( (string) sw_ksasi_get_setting( 'air_service_key' ) ),
			trim( (string) sw_ksasi_get_setting( 'service_key' ) ),
		) ) ) );
		if ( ! $keys ) {
			return new WP_Error( 'sw_air_no_key', '공공데이터포털 서비스 키가 설정되지 않았습니다. [설정 > KASI·기상청]에서 입력하세요.' );
		}
		foreach ( $keys as $key ) {
			$res = self::request_with_key( $operation, $params, $key );
			// 인증 오류(미등록 키·미신청 API)면 다음 키로 다시 시도
			if ( ! is_wp_error( $res ) || 'sw_air_auth' !== $res->get_error_code() ) {
				return $res;
			}
		}
		return $res;
	}

	private static function request_with_key( $operation, array $params, $service_key ) {
		$params += array( 'returnType' => 'json', 'pageNo' => 1 );
		$url     = self::BASE_URL . $operation . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ) . '&serviceKey=' . rawurlencode( $service_key );

		$res = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'sw_air_http', '에어코리아 API 요청 실패: ' . $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		$json = json_decode( $body, true );

		// 인증 오류: HTTP 403 + {"OpenAPI_ServiceResponse":{"cmmMsgHeader":{...}}} (XML 로 올 때도 있음)
		$hdr = $json['OpenAPI_ServiceResponse']['cmmMsgHeader'] ?? null;
		if ( ! $hdr && false !== strpos( $body, '<cmmMsgHeader>' ) && preg_match( '#<returnAuthMsg>([^<]*)</returnAuthMsg>.*?<returnReasonCode>([^<]*)#s', $body, $m ) ) {
			$hdr = array( 'returnAuthMsg' => $m[1], 'returnReasonCode' => $m[2] );
		}
		if ( $hdr ) {
			$msg = sprintf( '에어코리아 API 오류 (%s): %s', $hdr['returnReasonCode'] ?? $code, $hdr['returnAuthMsg'] ?? ( $hdr['errMsg'] ?? '' ) );
			if ( '30' === (string) ( $hdr['returnReasonCode'] ?? '' ) || '20' === (string) ( $hdr['returnReasonCode'] ?? '' ) ) {
				$msg .= ' — 공공데이터포털에서 해당 에어코리아 API를 활용신청했는지 확인하세요.';
			}
			return new WP_Error( 'sw_air_auth', $msg );
		}
		if ( 200 !== $code || ! isset( $json['response'] ) ) {
			return new WP_Error( 'sw_air_http', sprintf( '에어코리아 API 응답 오류 (HTTP %d)', $code ) );
		}
		$rc = (string) ( $json['response']['header']['resultCode'] ?? '00' );
		if ( '00' !== $rc && '03' !== $rc ) {
			return new WP_Error( 'sw_air_api', sprintf( '에어코리아 API 오류 (%s): %s', $rc, $json['response']['header']['resultMsg'] ?? '' ) );
		}
		$items = $json['response']['body']['items'] ?? array();
		return is_array( $items ) ? array_values( $items ) : array();
	}
}
