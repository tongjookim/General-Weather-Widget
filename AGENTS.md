# AGENTS.md

AI 코딩 에이전트와 기여자를 위한 개발 안내입니다. 사용자용 설명은 `README.md`를 보세요.

## 개요

- 워드프레스 플러그인. 빌드 단계, Composer, npm이 **없습니다**. PHP·CSS·JS 파일을 그대로 배포합니다.
- 외부 의존성 없음: 차트·아이콘은 서버에서 SVG로 만들고, JS는 바닐라 ES5입니다(`fetch`, `closest` 사용).
- 두 데이터원:
  - **KASI**: 공공데이터포털 XML API → `SW_KSASI_API` (`includes/class-sw-ksasi-api.php`)
  - **기상청 API허브**: typ01 텍스트 API → `SW_KMA_API` (`includes/kma/class-sw-kma-api.php`)
- API 명세 요약은 `openapi.md`(KASI), `kma-api.md`(기상청)에 있습니다. 실제 응답이 명세와 다른 부분은 아래 **함정** 절을 따르세요.

## 코드 구조와 흐름

| 파일 | 역할 |
|---|---|
| `sw-ksasi-sync.php` | 상수(`SW_KSASI_VERSION`, `SW_KSASI_PATH`, `SW_KSASI_URL`, `SW_KSASI_OPTION`), require, `plugins_loaded`에서 각 클래스 `init()` |
| `includes/functions.php` | `sw_ksasi_get_setting()`, 기본 설정, 시각 포맷, `sw_ksasi_flush_cache()`, 달 위상(`sw_ksasi_calc_moon`, `sw_ksasi_phase_name`, `sw_ksasi_moon_svg`) |
| `includes/class-sw-ksasi-admin.php` | 설정 → KASI·기상청 페이지, `sanitize()`, 연결 테스트, `admin-post` 액션(캐시 비우기, 날씨 페이지 생성) |
| `includes/class-sw-ksasi-shortcode.php` | `[sw_ksasi_riseset]`, `wp_ajax(_nopriv)_sw_ksasi_riseset` |
| `includes/kma/class-sw-kma-page.php` | `[sw_kma_weather]`. 섹션별 `render_*()` 메서드 |
| `includes/kma/class-sw-kma-data.php` | ASOS 지점 목록(권역별), 풍향·날씨 해석, 아이콘 SVG |

- 설정은 옵션 하나(`sw_ksasi_settings`, 배열)에 모두 저장합니다. 새 설정을 추가할 때는 아래 두 곳을 **함께** 고치세요.
  - `sw_ksasi_default_settings()`
  - `SW_KSASI_Admin::sanitize()`

  `sanitize()`는 `update_option()`을 코드에서 부를 때도 실행됩니다. 폼에 없는 값(예: `kma_page_id`)은 `$input`에 있으면 그 값을, 없으면 이전 값을 유지하도록 작성합니다.
- 캐시는 transient만 씁니다. 키 접두사는 반드시 `sw_ksasi_`로 시작해야 `sw_ksasi_flush_cache()`가 지웁니다. 기상청은 `sw_ksasi_kma_`를 씁니다.

## 코딩 규칙

- WordPress 코딩 표준: 탭 들여쓰기, `array()` 문법, 괄호 안 공백, Yoda 비교.
- 출력은 모두 이스케이프(`esc_html`, `esc_attr`, `esc_url`)합니다. 미리 만든 SVG를 `echo`하는 곳에만 `// phpcs:ignore WordPress.Security.EscapeOutput`을 붙입니다.
- 입력은 `absint`, `sanitize_text_field` 등으로 정리하고 화이트리스트로 검증합니다. 지점번호는 `SW_KMA_Data::station()`, 날짜는 `sw_ksasi_sanitize_date()`를 씁니다.
- 날짜·시각은 사이트 시간대 기준입니다: `wp_date()`, `wp_timezone()`, `DateTimeImmutable`. `date()`/`time()` 기반 로컬 계산은 쓰지 마세요.
- 사용자에게 보이는 문구는 한국어입니다. 방문자에게는 일반 오류 문구를, 관리자(`manage_options`)에게는 실제 오류 메시지를 보여줍니다.
- 에셋을 바꾸면 `sw-ksasi-sync.php`의 `Version` 헤더와 `SW_KSASI_VERSION`을 **같이** 올립니다. 이 값이 CSS/JS 캐시 버스터입니다.
- CSS는 BEM(`sw-ksasi__*`, `sw-kma__*`)을 쓰고 색은 CSS 변수로 정의합니다.
  - 날씨 페이지 변수는 `var(--alb-*, 기본값)`로 테마 토큰을 이어받습니다.
  - 테마 CSS가 플러그인 CSS보다 먼저 로드되므로, 테마에서 위젯을 덮어쓸 때는 선택자 우선순위를 높여야 합니다.

## 함정 (실제 API에서 확인된 동작)

### 공공데이터포털 (KASI)
- **서비스 키에 `sanitize_text_field()`를 쓰지 마세요.** 인코딩 키의 `%2B`, `%3D`를 퍼센트 옥텟으로 보고 삭제해 키가 잘립니다. 실제로 `SERVICE_KEY_IS_NOT_REGISTERED_ERROR`가 났던 원인입니다.
  - 키는 디코딩 형태로 저장하고, 요청할 때 `rawurlencode()` 합니다.
  - 디코딩에 `urldecode()`를 쓰면 `+`가 공백이 됩니다. 반드시 `rawurldecode()`를 쓰세요.
- 인증 오류는 HTTP 403과 함께 `OpenAPI_ServiceResponse/cmmMsgHeader` XML로 옵니다.
- 시각 값은 `"0731  "`, `"121619"`(HHMMSS), `"------"`(현상 없음) 등으로 섞여 옵니다. 항상 `sw_ksasi_format_time()`을 거치세요.
- `getLCRiseSetInfo`는 좌표를 1분 단위로 맞추고 `location`에 가까운 지명을 돌려주지만, 시각은 그 좌표 기준으로 계산합니다.

### 기상청 API허브 (typ01)
- 응답은 **EUC-KR** 텍스트이고, `#` 주석 줄과 공백으로 구분된 데이터 줄로 되어 있습니다. 주석 줄 앞에 공백이 붙어 있을 수 있습니다.
- 인증 실패나 미신청 API는 HTTP 401/403과 함께 JSON(`{"result":{"status":..,"message":..}}`)으로 옵니다.
- **컬럼 순서를 하드코딩하지 마세요.** `help=1`로 요청하면 `#  3. WD     : 풍향` 형식의 도움말이 오고, 파서는 이 번호로 컬럼을 매핑합니다.
- 예외: `sfc_norm1.php`(평년값)에는 번호 도움말이 없습니다. `# ST,STN, MM, DD, TA,...` 머리줄과 쉼표 구분 데이터로 오고, 컬럼 구성도 명세와 다릅니다. 파서가 이 머리줄도 처리합니다.
- 결측값은 `-9`, `-9.0`, `-99.0` 등입니다.
  - 기온류(`TA`, `TD`, `TS`, `TG`, `TE`, `PR`)는 음수가 정상일 수 있어 `-50` 이하만 결측으로 봅니다.
  - 나머지 숫자 항목은 음수를 모두 결측(`null`)으로 봅니다.
- 시간자료 풍향 `WD`는 **36방위(10° 단위)**입니다. 단위 줄에 `16`이라고 적혀 있지만 실제 값은 0~36입니다.
- 11월~3월에는 시간 강수량 `RN`이 3시간 단위(3, 6, … 24시)로만 오고, 나머지 시각은 결측입니다.
- `stn_inf.php`(지점정보)는 별도 활용신청이 필요합니다(403). 지점 좌표는 `kma_sfctm5.php`의 `LON`/`LAT`로 얻습니다.
- 정시 자료는 정각 몇 분 뒤에 올라옵니다. `all_latest()`는 비어 있으면 한 시간 전 자료를 씁니다.
- 페이지에서 쓰는 컬럼은 `SW_KMA_API::*_KEYS`에 넣어 두세요. 응답에 없어도 `null`로 채워져 경고가 나지 않습니다.

### 위젯
- AJAX 날짜 이동은 nonce 대신 숏코드 속성 JSON의 `wp_hash` 서명을 씁니다. 페이지 캐시 환경에서도 만료되지 않게 하기 위해서입니다.
  - 속성을 추가하면 `normalize()`에서 정규화한 값이 서명에 포함됩니다.
  - 부동소수는 JSON 왕복에서 표현이 달라지지 않도록 문자열로 보관합니다.
- 해↔달 전환 상태(`data-view`)는 바깥 `.sw-ksasi` 요소에 있습니다. AJAX는 내부 HTML만 교체하므로 상태가 유지됩니다.
- 달 SVG의 `id`는 요청마다 무작위로 만듭니다(`uniqid`). AJAX로 다시 그려도 같은 페이지의 다른 위젯과 겹치지 않게 하기 위해서입니다.

## 테스트

자동 테스트 스위트는 없습니다. 변경 후 아래를 확인하세요.

1. **문법 검사**
   ```bash
   for f in sw-ksasi-sync.php includes/*.php includes/kma/*.php; do php -l "$f"; done
   ```
2. **API 응답 없이 렌더링 확인**: WordPress를 로드하는 CLI 스크립트에서 `pre_http_request` 필터로 가짜 응답을 돌려주고 `do_shortcode()` 결과를 확인합니다.
   - 키 설정은 `option_sw_ksasi_settings` 필터로 주입하면 DB를 바꾸지 않습니다.
   - 가짜 응답은 실제 형식(EUC-KR, `help=1` 도움말, 결측값)을 흉내 내야 의미가 있습니다.
3. **실제 API 확인**: 키가 설정된 환경에서 `SW_KMA_API::hourly()`, `all_latest()`, `daily()`, `normal()`, `station_coords()`와 `SW_KSASI_API::get_area()`, `get_coords()`, `get_lunar_age()`를 호출해 봅니다. 테스트 후 `sw_ksasi_flush_cache()`로 캐시를 비우세요.
4. **화면 확인**: 헤드리스 Chromium으로 데스크톱(1200px)과 모바일(390px), 라이트/다크 모드 스크린샷을 찍어 봅니다.
   ```bash
   chromium-browser --headless --no-sandbox --window-size=390,1400 --screenshot=out.png <URL>
   ```
5. 관리자 화면 **설정 → KASI·기상청**에서 두 연결 테스트가 모두 정상인지 확인합니다.

## 보안·운영 주의

- API 키는 DB 옵션에만 저장합니다. 코드, 문서, 커밋, 로그에 키를 넣지 마세요. 디버그 출력에서도 키 길이 정도만 확인하세요.
- 외부 API 호출은 서버에서만 하고 반드시 캐시합니다. 공공 API에는 일일 호출 한도가 있습니다.
- 공개 AJAX 엔드포인트(`sw_ksasi_riseset`)는 서명 검증과 날짜 범위(±400일) 제한을 유지하세요. 이 제한을 풀면 임의 요청으로 외부 API 호출 수가 늘어납니다.
- 운영 중인 사이트의 글/페이지 상태(발행 등)나 테마 파일을 바꾸는 작업은 사용자 확인 후에 진행합니다.

## 테마 연동 (참고)

이 저장소 밖의 변경입니다. 라라프레스 AI랩 스킨의 `skins/ailab/home.php` 하단 "로봇 기사 오류 제보" 옆에 위젯이 들어가 있습니다.

- 호출 방식: `shortcode_exists()`로 확인한 뒤 `do_shortcode()`로 출력합니다.
- 노출 조건: 서비스 키가 있거나 관리자일 때만 보입니다.
- 스타일: 테마의 `ailab.css`에서 `.alb-bottom__side`, `.alb-astro`로 칸을 나누고 위젯 색을 스킨 토큰에 맞춥니다.

플러그인의 클래스명이나 숏코드 이름을 바꾸면 테마 쪽도 함께 수정해야 합니다.
