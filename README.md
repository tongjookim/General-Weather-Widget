# SW KASI Sync

한국천문연구원(KASI), 기상청 API허브, 한국환경공단 에어코리아의 공공 API로 **해·달 출몰시각 위젯**과 **지상관측 날씨 페이지**를 만드는 워드프레스 플러그인입니다.

- `[sw_ksasi_riseset]` — 일출/일몰 · 월출/월몰 위젯 (실제 달 이미지로 그린 위상 그래픽, 터치로 해↔달 전환, 날짜 이동)
- `[sw_kma_weather]` — 종관기상관측(ASOS) 날씨 페이지 (현재 날씨, 24시간 그래프, 대기질, 최근 7일, 해와 달, 전국 관측 현황)

| 항목 | 내용 |
|---|---|
| 버전 | 1.5.0 |
| 요구 사항 | WordPress 5.8+, PHP 7.4+ (`mbstring`, `libxml`) |
| 라이선스 | GPL-2.0-or-later ([LICENSE](LICENSE)) |

---

## 주요 기능

### 1. 해·달 출몰시각 위젯 `[sw_ksasi_riseset]`
- 한국천문연구원 **출몰시각 정보**(`RiseSetInfoService`)로 일출·남중·일몰, 월출·남중·월몰, 박명 시각을 보여줍니다.
- **해 카드**: 지평선 위 궤적에 현재 태양 위치를 표시하고, 낮의 길이를 보여줍니다.
- **달 카드**: NASA 달 표면 지도를 지구에서 본 모습으로 투영한 이미지에 그날의 위상(명암 경계)을 SVG 마스크로 합성합니다.
  - 밝기(%)는 천문 계산식(Meeus)으로 구합니다.
  - 월령은 KASI **월령 정보**(`LunPhInfoService`)를 우선 쓰고, 실패하면 계산값을 씁니다.
- `type="both"`(기본값)이면 해·달 카드가 같은 자리에 겹쳐 있고, **클릭/터치로 전환**됩니다. 키보드로도 조작할 수 있습니다.
- 이전/오늘/다음 버튼으로 날짜를 이동합니다(AJAX, 오늘 기준 ±400일).

### 2. 기상청 지상관측 날씨 페이지 `[sw_kma_weather]`
기상청 API허브 **지상관측** API를 중심으로, 대기질은 에어코리아 API를 사용합니다. 관측지점은 페이지 상단 선택 상자나 URL `?kma_stn=지점번호`로 바꿉니다.

| 섹션 | 내용 | 사용 API | 캐시 |
|---|---|---|---|
| 현재 날씨 | 기온, 날씨, 1시간 전 대비, 오늘 최고/최저, 체감온도, 습도, 바람, 강수, 기압, 이슬점, 시정, 운량, 적설 | `kma_sfctm3.php` | 10분 |
| 평년 비교 | 1991~2020 일 평년값과 비교 | `sfc_norm1.php` | 30일 |
| 최근 24시간 | 기온 선 + 강수 막대 SVG 그래프 (서버 렌더링) | `kma_sfctm3.php` | 10분 |
| 대기질 | 통합대기환경지수, 미세먼지·초미세먼지(1시간·24시간 평균), 오존·이산화질소·일산화탄소·아황산가스, 오늘·내일 예보 | 에어코리아 `getCtprvnRltmMesureDnsty`, `getMinuDustFrcstDspth` (+ 선택 `getMsrstnList`) | 15분 / 1시간 / 30일 |
| 최근 7일 | 최고/최저 범위 막대, 강수, 바람, 일조, 습도 | `kma_sfcdd3.php` | 3시간 |
| 해와 달 | 관측지점 **좌표 기준** 일출·일몰·월출·월몰, 달 위상 | KASI `getLCRiseSetInfo` + 지점 좌표(`kma_sfctm5.php`) | 설정값 / 30일 |
| 전국 관측 현황 | 97개 ASOS 지점 표, 열 정렬, 지점 클릭 시 이동 | `kma_sfctm2.php` | 10분 |

- 대기질 기준: 에어코리아 **측정소정보** API를 활용신청했으면 관측지점에서 가장 가까운 측정소(도로변 측정소 제외) 값을, 아니면 같은 시도 도시대기 측정소 평균을 보여줍니다. 예보는 관측지점이 속한 예보 권역(19개)의 등급입니다.
- 지점 변경과 그래프는 JavaScript 없이도 동작합니다. JS는 표 정렬과 선택 즉시 이동에만 씁니다.
- 테마에 `--alb-*` 색상 토큰(라라프레스 AI랩 스킨)이 있으면 그 색과 다크 모드(`html.dark`)를 그대로 따릅니다. 없으면 기본 색을 씁니다.

---

## 설치

```bash
cd wp-content/plugins
git clone https://github.com/tongjookim/General-Weather-Widget.git sw-ksasi-sync
# 또는: gh repo clone tongjookim/General-Weather-Widget sw-ksasi-sync
```

워드프레스 관리자 → **플러그인**에서 *SW KASI Sync*를 활성화합니다.

## API 키 발급

두 서비스의 키는 서로 **다릅니다**.

| 서비스 | 발급처 | 활용신청할 API | 설정 항목 |
|---|---|---|---|
| 한국천문연구원 | [공공데이터포털](https://www.data.go.kr/) | `한국천문연구원_출몰시각 정보` (필수), `한국천문연구원_월령 정보` (선택) | 서비스 키 (ServiceKey) |
| 한국환경공단 | [공공데이터포털](https://www.data.go.kr/) (같은 키) | `한국환경공단_에어코리아_대기오염정보` (대기질 섹션에 필요), `한국환경공단_에어코리아_측정소정보` (선택, 가까운 측정소 기준) | 서비스 키 (공용) |
| 기상청 | [기상청 API허브](https://apihub.kma.go.kr/) | `지상관측` | 인증키 (authKey) |

- 공공데이터포털 키는 **인코딩/디코딩 어느 쪽을 붙여넣어도** 됩니다. 저장할 때 디코딩 형태로 정규화합니다.
- 승인 직후에는 키가 반영되기까지 1~2시간 걸릴 수 있습니다. 그동안 `SERVICE_KEY_IS_NOT_REGISTERED_ERROR`가 납니다.
- 월령 API를 신청하지 않아도 동작합니다. 실패하면 6시간 동안 다시 호출하지 않고 계산값을 씁니다.
- 에어코리아 측정소정보 API도 선택입니다. 실패하면 12시간 동안 다시 호출하지 않고 시도 평균을 씁니다. 신청 후 바로 반영하려면 **캐시 비우기**를 누르세요.
- 에어코리아 개발계정은 하루 500회까지 호출할 수 있습니다. 이 플러그인은 전국 측정값 1회(15분 캐시)와 예보 1회(1시간 캐시)만 호출하므로 개발계정으로 충분합니다.

## 설정

관리자 → **설정 → KASI·기상청**

| 항목 | 설명 |
|---|---|
| 서비스 키 | 공공데이터포털 키(KASI·에어코리아 공용). 저장하면 상단에 **연결 테스트** 결과가 표시됩니다 |
| 기본 지역 | 위젯에 `location` 속성이 없을 때 쓰는 지역명 (기본 `서울`) |
| 캐시 시간 | KASI 응답 캐시 시간 (1~168시간, 기본 12) |
| 인증키 (authKey) | 기상청 API허브 키. 저장하면 연결 테스트 결과가 표시됩니다 |
| 기본 관측지점 | 날씨 페이지 기본 지점 (기본 서울 108) |
| 날씨 페이지 만들기 | `[sw_kma_weather]`가 들어간 페이지(슬러그 `weather`)를 초안으로 만듭니다 |
| 캐시 비우기 | 플러그인이 만든 모든 캐시(transient) 삭제 |

키를 바꾸면 캐시가 자동으로 비워집니다.

---

## 숏코드

### `[sw_kma_weather]`

| 속성 | 기본값 | 설명 |
|---|---|---|
| `stn` | 설정의 기본 관측지점 | ASOS 지점번호. URL의 `?kma_stn=`이 있으면 그 값이 우선합니다 |

```text
[sw_kma_weather]
[sw_kma_weather stn="159"]
```

### `[sw_ksasi_riseset]`

| 속성 | 기본값 | 설명 |
|---|---|---|
| `location` | 설정의 기본 지역 | 지역명 (예: `부산`) |
| `lat`, `lng` | — | 위도·경도(10진수). 지정하면 `location`보다 우선합니다 |
| `label` | — | 위경도 조회 시 표시할 이름 |
| `date` | 오늘 | `YYYYMMDD` |
| `type` | `both` | `sun` / `moon` / `both`(터치 전환) |
| `twilight` | `no` | `yes`면 시민·항해·천문 박명 표 추가 |
| `nav` | `yes` | `no`면 날짜 이동 버튼 숨김 |
| `title` | `오늘의 해와 달` | 위젯 제목 |
| `theme` | `light` | `light` / `dark` |

```text
[sw_ksasi_riseset]
[sw_ksasi_riseset location="부산" twilight="yes"]
[sw_ksasi_riseset lat="37.5665" lng="126.9780" label="서울시청" type="moon"]
[sw_ksasi_riseset date="20261225" nav="no" theme="dark"]
```

테마 템플릿에서 쓰려면 다음처럼 호출합니다.

```php
<?php if ( shortcode_exists( 'sw_ksasi_riseset' ) ) echo do_shortcode( '[sw_ksasi_riseset]' ); ?>
```

---

## 파일 구조

```text
sw-ksasi-sync/
├── sw-ksasi-sync.php                 # 플러그인 메인 (상수, require, 훅 등록, 버전)
├── includes/
│   ├── functions.php                 # 설정 헬퍼, 시각 포맷, 캐시 삭제, 달 위상 계산·SVG
│   ├── class-sw-ksasi-api.php        # KASI 출몰시각·월령 API (XML)
│   ├── class-sw-ksasi-admin.php      # 설정 페이지, 연결 테스트, 날씨 페이지 생성
│   ├── class-sw-ksasi-shortcode.php  # [sw_ksasi_riseset] + 날짜 이동 AJAX
│   ├── air/
│   │   └── class-sw-air-api.php      # 에어코리아 실시간 측정·예보·측정소 좌표 (JSON)
│   └── kma/
│       ├── class-sw-kma-api.php      # 기상청 API허브 typ01 텍스트 파서·클라이언트
│       ├── class-sw-kma-data.php     # ASOS 지점 목록, 풍향·날씨 코드, 아이콘 SVG
│       └── class-sw-kma-page.php     # [sw_kma_weather] 페이지 렌더링
├── assets/
│   ├── css/sw-ksasi-sync.css         # 위젯 스타일
│   ├── css/sw-kma-weather.css        # 날씨 페이지 스타일
│   ├── js/sw-ksasi-sync.js           # 위젯 날짜 이동, 해↔달 전환
│   ├── js/sw-kma-weather.js          # 지점 선택 이동, 표 정렬
│   └── img/moon.webp                 # 달 앞면 정사영 이미지 (320px, NASA SVS)
├── openapi.md                        # KASI OpenAPI 명세 요약
├── kma-api.md                        # 기상청 지상관측 API 명세 요약
├── airkorea.md                       # 에어코리아 OpenAPI 명세 요약
├── README.md
└── AGENTS.md                         # AI 코딩 에이전트/기여자용 개발 안내
```

## 데이터와 캐시

- 모든 API 응답은 **서버에서** 호출해 transient로 캐시합니다. 브라우저는 외부 API를 직접 호출하지 않으므로 키가 노출되지 않습니다.
- 캐시 키 접두사는 `sw_ksasi_`(KASI), `sw_ksasi_kma_`(기상청), `sw_ksasi_air_`(에어코리아)이고, 플러그인을 비활성화하면 모두 삭제됩니다.
- 관측값은 품질검사 전 자료라 실제와 다를 수 있습니다. 에어코리아 실시간 측정값도 인증받지 않은 자료이며, 이용 약관에 따라 화면에 출처와 오류 가능성을 표시합니다.
- 11월~3월에는 기상청 시간 강수량(RN)이 3시간 단위(3, 6, … 24시)로만 제공됩니다.

## 출처 및 저작권

- 해·달 출몰시각, 월령: **한국천문연구원** (공공데이터포털 OpenAPI)
- 지상관측 자료: **기상청 API허브**
- 대기질 자료: **환경부·한국환경공단 에어코리아** (공공데이터포털 OpenAPI)
- 달 이미지: **NASA's Scientific Visualization Studio**, CGI Moon Kit (LRO LROC 컬러 모자이크, 퍼블릭 도메인). 위젯·페이지에 출처를 표시합니다.
- 달 위상 계산: J. Meeus, *Astronomical Algorithms* 48장 간략식

## 변경 이력

| 버전 | 내용 |
|---|---|
| 1.5.0 | 날씨 페이지에 에어코리아 대기질 섹션 추가 (실시간 측정값, 오늘·내일 예보) |
| 1.4.0 | 날씨 페이지에 관측지점 좌표 기준 해와 달 섹션 추가 |
| 1.3.x | 기상청 지상관측 날씨 페이지 `[sw_kma_weather]`. 평년값 CSV 머리줄 파싱, 모바일 그래프 스크롤 수정 |
| 1.2.0 | 위젯 해↔달 카드 터치 전환 |
| 1.1.0 | 실제 달 이미지 위상 그래픽, 월령 API 연동 |
| 1.0.x | 출몰시각 위젯 최초 버전. 서비스 키 인코딩 처리 수정 |
