<?php
/**
 * 한국천문연구원 출몰시각 정보제공 서비스(RiseSetInfoService) API 클라이언트
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SW_KSASI_API {

	const BASE_URL = 'http://apis.data.go.kr/B090041/openapi/service/';

	/**
	 * 지역명으로 해/달 출몰시각 조회 (getAreaRiseSetInfo)
	 *
	 * @param string $location 지역명 (예: 서울).
	 * @param string $locdate  Ymd.
	 * @return array|WP_Error
	 */
	public static function get_area( $location, $locdate ) {
		return self::request( 'RiseSetInfoService/getAreaRiseSetInfo', array(
			'locdate'  => $locdate,
			'location' => $location,
		) );
	}

	/**
	 * 위경도로 해/달 출몰시각 조회 (getLCRiseSetInfo)
	 *
	 * @param float  $lat     위도(10진수).
	 * @param float  $lng     경도(10진수).
	 * @param string $locdate Ymd.
	 * @return array|WP_Error
	 */
	public static function get_coords( $lat, $lng, $locdate ) {
		return self::request( 'RiseSetInfoService/getLCRiseSetInfo', array(
			'locdate'   => $locdate,
			'longitude' => number_format( (float) $lng, 4, '.', '' ),
			'latitude'  => number_format( (float) $lat, 4, '.', '' ),
			'dnYn'      => 'Y',
		) );
	}

	/**
	 * 월령 조회 (LunPhInfoService/getLunPhInfo)
	 *
	 * 월령 API 는 출몰시각 API 와 별도로 활용신청해야 한다. 실패하면 일정 시간 재시도하지 않고
	 * null 을 반환하며, 호출 측에서 천문 계산값으로 대체한다.
	 *
	 * @param string $locdate Ymd.
	 * @return float|null
	 */
	public static function get_lunar_age( $locdate ) {
		if ( get_transient( 'sw_ksasi_lunph_fail' ) ) {
			return null;
		}
		$item = self::request( 'LunPhInfoService/getLunPhInfo', array(
			'solYear'  => substr( $locdate, 0, 4 ),
			'solMonth' => substr( $locdate, 4, 2 ),
			'solDay'   => substr( $locdate, 6, 2 ),
		) );
		if ( is_wp_error( $item ) || ! isset( $item['lunAge'] ) || ! is_numeric( $item['lunAge'] ) ) {
			set_transient( 'sw_ksasi_lunph_fail', 1, 6 * HOUR_IN_SECONDS );
			return null;
		}
		return (float) $item['lunAge'];
	}

	/**
	 * 공통 요청 + 캐시 처리
	 *
	 * @return array|WP_Error 응답 item 배열
	 */
	private static function request( $operation, array $params ) {
		$service_key = trim( (string) sw_ksasi_get_setting( 'service_key' ) );
		if ( '' === $service_key ) {
			return new WP_Error( 'sw_ksasi_no_key', '서비스 키가 설정되지 않았습니다. [설정 > KASI 출몰시각]에서 공공데이터포털 서비스 키를 입력하세요.' );
		}

		$cache_key = 'sw_ksasi_' . md5( $operation . wp_json_encode( $params ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		// 키는 디코딩 형태(+, /, =)로 저장된다. 인코딩 키가 남아 있으면 먼저 디코딩해 이중 인코딩을 막는다.
		// urldecode() 는 '+' 를 공백으로 바꾸므로 반드시 rawurldecode() 를 사용한다.
		if ( false !== strpos( $service_key, '%' ) ) {
			$service_key = rawurldecode( $service_key );
		}
		$url = self::BASE_URL . $operation . '?ServiceKey=' . rawurlencode( $service_key )
			. '&' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );

		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/xml' ),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sw_ksasi_http', 'API 요청 실패: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( 200 !== (int) $code || '' === $body ) {
			return new WP_Error( 'sw_ksasi_http', sprintf( 'API 응답 오류 (HTTP %d)', $code ) );
		}

		$item = self::parse( $body );
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$hours = max( 1, (int) sw_ksasi_get_setting( 'cache_hours' ) );
		set_transient( $cache_key, $item, $hours * HOUR_IN_SECONDS );

		return $item;
	}

	/**
	 * XML 응답 파싱
	 *
	 * @return array|WP_Error
	 */
	private static function parse( $body ) {
		$prev = libxml_use_internal_errors( true );
		$xml  = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( false === $xml ) {
			return new WP_Error( 'sw_ksasi_parse', 'API 응답(XML)을 해석할 수 없습니다.' );
		}

		// 인증키 오류 등 게이트웨이 에러 응답 (OpenAPI_ServiceResponse)
		if ( isset( $xml->cmmMsgHeader ) ) {
			$msg = (string) ( $xml->cmmMsgHeader->returnAuthMsg ?: $xml->cmmMsgHeader->errMsg );
			return new WP_Error( 'sw_ksasi_auth', 'API 오류: ' . $msg );
		}

		$result_code = (string) $xml->header->resultCode;
		if ( '' !== $result_code && '00' !== $result_code ) {
			return new WP_Error( 'sw_ksasi_api', sprintf( 'API 오류 (%s): %s', $result_code, (string) $xml->header->resultMsg ) );
		}

		$item = $xml->body->items->item ?? null;
		if ( ! $item || ! $item->count() ) {
			return new WP_Error( 'sw_ksasi_empty', '해당 날짜/지역의 출몰시각 데이터가 없습니다. 지역명을 확인하세요.' );
		}

		$data = array();
		foreach ( $item->children() as $name => $value ) {
			$data[ (string) $name ] = trim( (string) $value );
		}
		return $data;
	}
}
