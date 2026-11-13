# 한국환경공단 에어코리아 OpenAPI 종합 안내서

본 문서는 한국환경공단 에어코리아에서 제공하는 다양한 대기환경 OpenAPI의 기술문서와 신청 가이드를 종합하여 작성된 통합 레퍼런스 문서입니다.

## 목차
1. [공통 기본 정보](#1-공통-기본-정보)
2. [OpenAPI 계정 신청 가이드 요약](#2-openapi-계정-신청-가이드-요약)
3. [서비스 명세 (API 목록)](#3-서비스-명세-api-목록)
    - 3.1. 대기오염정보 조회 서비스
    - 3.2. 대기오염통계 서비스
    - 3.3. 통합대기환경지수(CAI) 조회 서비스
    - 3.4. 고농도 초미세먼지(50초과) 예보 정보 조회 서비스
    - 3.5. 미세먼지 경보 정보 조회 서비스
    - 3.6. 오존·황사 발생정보 조회 서비스
    - 3.7. 측정소정보 조회 서비스
    - 3.8. 사용자 지원 서비스 (트래픽 조회)
4. [공통 에러 코드](#4-공통-에러-코드)

---

## 1. 공통 기본 정보

### 1.1 항목별 실시간 자료 측정 단위
| 항목 | SO2 (아황산가스) | CO (일산화탄소) | O3 (오존) | NO2 (이산화질소) | PM10 (미세먼지) | PM2.5 (초미세먼지) |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **단위** | ppm | ppm | ppm | ppm | ㎍/㎥ | ㎍/㎥ |

### 1.2 등급 (Grade) 값의 의미
*적용 항목: khaiGrade, so2Grade, coGrade, o3Grade, no2Grade, pm10Grade, pm25Grade 등*
| 등급 | 좋음 | 보통 | 나쁨 | 매우나쁨 |
| :--- | :---: | :---: | :---: | :---: |
| **Grade 값** | 1 | 2 | 3 | 4 |

### 1.3 공통 요청 파라미터
거의 모든 API는 다음의 공통 파라미터를 갖습니다.
*   `serviceKey` (필수): 공공데이터포털에서 발급받은 인증키 (URL Encode)
*   `returnType` (옵션): `xml` 또는 `json` (기본값 xml)
*   `numOfRows` (옵션): 한 페이지 결과 수
*   `pageNo` (옵션): 페이지 번호

---

## 2. OpenAPI 계정 신청 가이드 요약

1. **개발계정 신청 (일일 500회)**
   - 공공데이터포털(data.go.kr) 접속 및 로그인 -> '에어코리아' 검색
   - '오픈 API' 탭에서 원하는 서비스 선택 후 **[활용신청]** 버튼 클릭
   - 활용목적 작성 (시스템 동기화에 1~2시간 소요)
2. **운영계정 신청 (일일 10,000회)**
   - 마이페이지 -> API 신청 내역에서 해당 API 선택 -> **[운영계정 신청]**
   - 서비스명, URL, 출처 표기 여부 작성 및 **활용사례정보(개발 프로그램 대표 화면, 출처가 명시된 화면 캡처 등) 2가지 증빙 첨부 필수**
   - 담당자 심의 후 승인 (1일 ~ 1주일 소요)

---

## 3. 서비스 명세 (API 목록)

### 3.1. 대기오염정보 조회 서비스
**서비스 URL:** `http://apis.data.go.kr/B552584/ArpltnInforInqireSvc`

#### ① 측정소별 실시간 측정정보 조회 (`/getMsrstnAcctoRltmMesureDnsty`)
*   **설명:** 특정 측정소의 실시간 측정정보 조회 (매시 15분 내외 갱신)
*   **추가 요청 파라미터:**
    *   `stationName` (필수): 측정소명 (예: 종로구)
    *   `dataTerm` (필수): 데이터기간 (DAILY, MONTH, 3MONTH)
    *   `ver` (옵션): `1.0`(PM2.5포함), `1.1`(24시간예측이동농도 포함), `1.2`(측정망 포함), `1.3`(1시간등급 포함), `1.4`(측정소코드 포함), `1.5`(소수점 확대)
*   **주요 응답 항목:** `dataTime`, `mangName`, `so2Value`, `coValue`, `o3Value`, `no2Value`, `pm10Value`, `pm25Value`, `khaiValue`, `khaiGrade` 등

#### ② 통합대기환경지수 나쁨 이상 측정소 목록조회 (`/getUnityAirEnvrnIdexSnstiveAboveMsrstnList`)
*   **설명:** 통합대기환경지수가 나쁨 등급 이상인 측정소 목록
*   **추가 요청 파라미터:** `ver=1.0` (측정소코드 포함 시)
*   **주요 응답 항목:** `stationName`, `stationCode`, `addr`

#### ③ 시도별 실시간 측정정보 조회 (`/getCtprvnRltmMesureDnsty`)
*   **설명:** 시도별 측정소의 측정값과 지수 정보 조회
*   **추가 요청 파라미터:**
    *   `sidoName` (필수): 전국, 서울, 부산, 대구, 인천, 광주, 대전, 울산, 경기, 강원, 충북, 충남, 전북, 전남, 경북, 경남, 제주, 세종, 전남광주
    *   `ver` (옵션): `1.0` ~ `1.5` (측정소별 조회와 동일)
*   **주요 응답 항목:** `stationName`, `sidoName`, `dataTime`, 각종 오염물질 Value 및 Grade

#### ④ 대기질 예보통보 조회 (`/getMinuDustFrcstDspth`)
*   **설명:** 대기질(미세먼지/오존) 예보통보 조회 (매일 4회 발표)
*   **추가 요청 파라미터:**
    *   `searchDate` (옵션): 조회 날짜 (예: 2020-11-14)
    *   `informCode` (옵션): PM10, PM25, O3
*   **주요 응답 항목:** `dataTime`, `informCode`, `informOverall`(예보개황), `informCause`(발생원인), `informGrade`(예보등급), `imageUrl1~9`(예측모델결과 사진)

#### ⑤ 초미세먼지 주간예보 조회 (`/getMinuDustWeekFrcstDspth`)
*   **설명:** 대기질 전망과 초미세먼지 주간예보 정보 조회
*   **추가 요청 파라미터:** `searchDate` (옵션, 예: 2020-11-09)
*   **주요 응답 항목:** `frcstOneCn` ~ `frcstFourCn` (1~4일차 예보), `presnatnDt`(발표일시), `gwthcnd`(대기질 전망)

---

### 3.2. 대기오염통계 서비스
**서비스 URL:** `http://apis.data.go.kr/B552584/ArpltnStatsSvc`

#### ① 시도별 실시간 평균정보 조회 (`/getCtprvnMesureLIst`)
*   **설명:** 시도별 일반 항목의 시간 및 일평균/지역 평균 정보
*   **추가 요청 파라미터:**
    *   `itemCode` (필수): SO2, CO, O3, NO2, PM10, PM25
    *   `dataGubun` (필수): HOUR (시간평균), DAILY (일평균)
    *   `searchCondition` (옵션): WEEK (일주일), MONTH (한달) *일평균일 때 사용*
*   **주요 응답 항목:** `dataTime`, `itemCode`, `dataGubun`, `seoul`, `busan` 등 시도명 영문 필드 (평균값)

#### ② 시군구별 실시간 평균정보 조회 (`/getCtprvnMesureSidoLIst`)
*   **설명:** 시도 내 시군구별 평균농도 조회
*   **추가 요청 파라미터:**
    *   `sidoName` (필수): 시도명
    *   `searchCondition` (필수): HOUR, DAILY
*   **주요 응답 항목:** `cityName`, `cityNameEng`, `so2Value` 등 각종 오염물질 평균농도

#### ③ 측정소별 실시간 일평균 정보 조회 (`/getMsrstnAcctoRDyrg`)
*   **추가 요청 파라미터:** `inqBginDt`(조회시작일, YYYYMMDD), `inqEndDt`(조회종료일), `msrstnName`(측정소명, 옵션)
#### ④ 측정소별 실시간 월평균 정보 조회 (`/getMsrstnAcctoRMmrg`)
*   **추가 요청 파라미터:** `inqBginMm`(조회시작월, YYYYMM), `inqEndMm`(조회종료월), `msrstnName`(측정소명, 옵션)

---

### 3.3. 통합대기환경지수(CAI) 조회 서비스
**서비스 URL:** `http://apis.data.go.kr/B552584/RltmKhaiInfoSvc`

#### ① 측정소별 실시간 통합대기환경지수(CAI) 조회 (`/getMsrstnKhaiRltmDnsty`)
*   **설명:** 측정소명으로 해당 실시간 통합대기환경지수(CAI) 정보 조회
*   **추가 요청 파라미터:** `stationName` (필수)
*   **주요 응답 항목:** `khaiValue`(수치), `khaiGrade`(등급: 1~4), `khaiItem`(주 오염물질)

---

### 3.4. 고농도 초미세먼지(50초과) 예보 정보 조회 서비스
**서비스 URL:** `http://apis.data.go.kr/B552584/MinuDustFrcstDspthSvc`

#### ① 고농도 초미세먼지(50초과) 예보 정보 조회 (`/getMinuDustFrcstDspth50Over`)
*   **설명:** 19개 권역의 초미세먼지 50㎍/㎥ 초과여부 통보 (매일 4회 발표)
*   *(상세 파라미터 및 응답 구조는 Swagger 문서 참고)*

---

### 3.5. 미세먼지 경보 정보 조회 서비스
**서비스 URL:** `http://apis.data.go.kr/B552584/UlfptcaAlarmInqireSvc`

#### ① 미세먼지 경보 현황 정보 조회 (`/getUlfptcaAlarmInfo`)
*   **설명:** 미세먼지에 대한 지역별 주의보/경보 발령 및 해제 정보 조회
*   **추가 요청 파라미터:** `year` (필수, 예: 2020), `itemCode` (옵션, PM10 또는 PM25)
*   **주요 응답 항목:** `sn`(관리번호), `districtName`, `moveName`(권역명), `issueGbn`(주의보/경보), `issueDate`, `issueTime`, `issueVal`, `clearDate`, `clearTime`, `clearVal`

---

### 3.6. 오존·황사 발생정보 조회 서비스
**서비스 URL:** `http://apis.data.go.kr/B552584/OzYlwsndOccrrncInforInqireSvc`

#### ① 오존주의보 발생정보 조회 (`/getOzAdvsryOccrrncInfo`)
*   **추가 요청 파라미터:** `year` (옵션, 예: 2020)
*   **주요 응답 항목:** `sn`, `dataDate`, `districtName`, `moveName`, `issueTime`, `issueVal`, `clearTime`, `clearVal`, `maxVal`, `issueLvl`
#### ② 황사주의보 발생정보 조회 (`/getYlwsndAdvsryOccrrncInfo`)
*   **추가 요청 파라미터:** `year` (옵션)
*   **주요 응답 항목:** `dataTime`, `tmCnt`(발령회차), `tmArea`(발령지역)

---

### 3.7. 측정소정보 조회 서비스
**서비스 URL:** `http://apis.data.go.kr/B552584/MsrstnInfoInqireSvc`

#### ① 측정소 목록 조회 (`/getMsrstnList`)
*   **설명:** 측정소 주소 또는 명칭으로 측정소 정보 조회
*   **추가 요청 파라미터:** `addr` (옵션), `stationName` (옵션)
    *   *버전(`ver=1.1`)* 호출 시 `dmX`, `dmY` 좌표가 올바르게(정상 위치로) 제공됨 (이전 버전은 X, Y가 뒤바뀜 주의)
*   **주요 응답 항목:** `stationName`, `addr`, `year`, `mangName`, `item`, `dmX`, `dmY`

#### ② 근접측정소 목록 조회 (`/getNearbyMsrstnList`)
*   **설명:** TM좌표 또는 도로명주소 API 좌표를 기반으로 가까운 측정소 조회
*   **추가 요청 파라미터:** `tmX` (필수), `tmY` (필수), `ver` (옵션)
    *   `ver=1.0`: 도로명주소검색 API의 X, Y 좌표 사용
    *   `ver=1.1`: TM좌표 기반, 측정소 코드 포함 응답
    *   `ver=1.2`: 도로명주소 API 좌표 기반, 측정소 코드 포함 응답
*   **주요 응답 항목:** `stationName`, `addr`, `tm`(거리 km)

#### ③ TM 기준좌표 조회 (`/getTMStdrCrdnt`)
*   **추가 요청 파라미터:** `umdName` (필수, 읍면동명)
*   **주요 응답 항목:** `sidoName`, `sggName`, `umdName`, `tmX`, `tmY`

---

### 3.8. 사용자 지원 서비스 (트래픽 조회)
**서비스 URL:** `http://apis.data.go.kr/B552584/UserSportSvc`

#### ① 트래픽 정보 조회 (`/getSvckeyDalyStats`)
*   **설명:** 서비스키에 따른 일일 API 호출 건수 조회
*   **추가 요청 파라미터:** `searchDate` (필수, 예: 2020-12-24)
*   **주요 응답 항목:** `conectDe`(접속일시), `conectOprtinNm`(접속서비스), `conectCo`(접속횟수)

---

## 4. 공통 에러 코드

| 에러코드 | 에러메시지 | 설명 |
| :--- | :--- | :--- |
| **00** | NORMAL SERVICE | 정상 (에러 아님) |
| **01** | Application Error | 서비스 제공 상태가 원활하지 않음 |
| **02** | DB Error | 서비스 제공 상태가 원활하지 않음 |
| **03** | No Data | 데이터 없음 에러 |
| **04** | HTTP Error | 서비스 제공 상태가 원활하지 않음 |
| **05** | service time out | 서비스 제공 상태가 원활하지 않음 |
| **10** | 잘못된 요청 파라미터 에러 | 파라미터값 요청이 잘못됨 |
| **11** | 필수 요청 파라미터 없음 | 필수 파라미터가 누락됨 |
| **12** | 해당 오픈API 서비스가 없거나 폐기됨 | OpenAPI 호출시 URL이 잘못됨 |
| **20** | 서비스 접근 거부 | 활용 신청하지 않은 OpenAPI 호출 |
| **22** | 서비스 요청 제한 횟수 초과 에러 | 하루 트래픽 제한을 초과함 |
| **30** | 등록하지 않은 서비스키 | 잘못된 서비스키 또는 URL 인코딩 안됨 |
| **31** | 서비스키 사용 기간 만료 | OpenAPI 사용기간 만료 |
| **32** | 등록하지 않은 도메인명 또는 IP주소 | 활용신청 서버 IP와 실제 호출 IP 상이 |
| **34** | 보고서가 등록 되지 않음 | 개발보고서가 아직 승인되지 않은 경우 (운영계정 관련) |

---
> **※ 주의사항 (공통 약관 요약)**
> - 데이터는 "인증받지 않은 실시간 자료"이므로 오류나 미수신이 발생할 수 있음을 서비스 화면에 명시해야 합니다. (출처 및 데이터 오류 가능성 표기 의무)
> - 운영계정 신청 시 반드시 개발 프로그램 화면 및 출처 명시 캡처 증빙을 첨부해야 합니다.
