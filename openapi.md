# 한국천문연구원 천문우주정보 OpenAPI 활용 가이드

본 문서는 공공데이터포털(data.go.kr)을 통해 제공되는 **한국천문연구원(천문우주정보)**의 OpenAPI 5종에 대한 개발자 연동 가이드입니다. 

## 📋 제공 서비스 목록
1. **월령 정보제공 서비스** (`LunPhInfoService`)
2. **음양력 정보제공 서비스** (`LrsrCldInfoService`)
3. **천문현상 정보제공 서비스** (`AstroEventInfoService`)
4. **출몰시각 정보제공 서비스** (`RiseSetInfoService`)
5. **태양고도 정보제공 서비스** (`SrAltudeInfoService`)

## 💡 공통 정보
* **제공기관**: 한국천문연구원 천문전산융합센터
* **인증방식**: 공공데이터포털(data.go.kr)에서 발급받은 서비스 Key (Request Parameter: `ServiceKey`)
* **데이터 포맷**: XML
* **인터페이스 표준**: REST (GET)
* **Base URL**: `http://apis.data.go.kr/B090041/openapi/service/`

---

## 1. 월령 정보제공 서비스 (`LunPhInfoService`)
양력일을 기준으로 월령 정보를 조회하는 서비스입니다. (데이터 갱신주기: 연 1회)

### 1.1. 월령 정보조회 (`getLunPhInfo`)
**[Request Parameter]**
| 항목명 | 영문명 | 타입 | 필수여부 | 설명 | 샘플데이터 |
|---|---|---|:---:|---|---|
| 연 | `solYear` | String(4) | **Y** | 조회할 양력 연도 | 2015 |
| 월 | `solMonth` | String(2) | **Y** | 조회할 양력 월 | 09 |
| 일 | `solDay` | String(2) | N | 조회할 양력 일 | 22 |

**[Response Field]**
* `Item`: `solYear`(연), `solMonth`(월), `solDay`(일), `solWeek`(요일), `lunAge`(월령)

**[요청 예시]**
```http
GET /LunPhInfoService/getLunPhInfo?solYear=2015&solMonth=01&solDay=01&ServiceKey=YOUR_SERVICE_KEY
```

---

## 2. 음양력 정보제공 서비스 (`LrsrCldInfoService`)
음력/양력 변환, 특정 음력일 정보, 율리우스적일 정보를 조회하는 서비스입니다. (데이터 갱신주기: 연 1회)

### 2.1. 음력일 정보조회 (`getLunCalInfo`)
양력일을 기준으로 음력날짜, 요일, 간지, 윤달 여부, 율리우스 적일 등을 조회합니다.

**[Request Parameter]**
| 항목명 | 영문명 | 필수여부 | 설명 | 샘플데이터 |
|---|---|:---:|---|---|
| 연 | `solYear` | **Y** | 양력 연도 | 2015 |
| 월 | `solMonth` | **Y** | 양력 월 | 09 |
| 일 | `solDay` | N | 양력 일 | 22 |

**[Response Field]**
* `Item`: `lunYear`, `lunMonth`, `lunDay`, `solYear`, `solMonth`, `solDay`, `solLeapyear`, `lunLeapmonth`, `solWeek`, `lunSecha`(세차), `lunWolgeon`(월건), `lunIljin`(일진), `lunNday`, `solJd`(율리우스적일)

### 2.2. 양력일 정보조회 (`getSolCalInfo`)
음력일을 기준으로 양력날짜 및 관련 정보를 조회합니다. (`getLunCalInfo`와 응답 항목 동일)
* **Req**: `lunYear`(Y), `lunMonth`(Y), `lunDay`(N)

### 2.3. 특정 음력일 정보조회 (`getSpcifyLunCalInfo`)
* **Req**: `fromSolYear`(검색시작년도, Y), `toSolYear`(검색종료년도, Y), `lunMonth`(음력월, Y), `lunDay`(음력일, N), `leapMonth`(윤달구분-평/윤, N)

### 2.4. 율리우스적일 정보조회 (`getJulDayInfo`)
* **Req**: `solJd`(율리우스적일, Y)

---

## 3. 천문현상 정보제공 서비스 (`AstroEventInfoService`)
월별로 시각, 천문현상 등의 정보를 제공합니다. (데이터 갱신주기: 연 1회)

### 3.1. 천문현상 정보조회 (`getAstroEventInfo`)

**[Request Parameter]**
| 항목명 | 영문명 | 필수여부 | 설명 | 샘플데이터 |
|---|---|:---:|---|---|
| 연 | `solYear` | **Y** | 검색 연도 | 2015 |
| 월 | `solMonth` | **Y** | 검색 월 | 09 |

**[Response Field]**
* `Item`: `locdate`(날짜), `seq`(순번), `astroTitle`(천문현상명), `astroTime`(현상시간), `astroEvent`(현상 상세), `remarks`(비고)

---

## 4. 출몰시각 정보제공 서비스 (`RiseSetInfoService`)
지역별 및 위치(위경도)별 해와 달의 출몰시각, 박명시각 등을 제공합니다. (데이터 갱신주기: 연 1회)

### 4.1. 지역별 해달 출몰시각 정보조회 (`getAreaRiseSetInfo`)
* **Req**: `locdate`(날짜, Y), `location`(지역명 ex: 서울, Y)

### 4.2. 위치별 해달 출몰시각 정보조회 (`getLCRiseSetInfo`)
* **Req**: `locdate`(날짜, Y), `longitude`(경도, Y), `latitude`(위도, Y), `dnYn`(10진수 여부 Y/N, N)

**[공통 Response Field]**
* `locdate`, `location`, `longitude`, `latitude`, `longitudeNum`, `latitudeNum`
* `sunrise`(일출), `suntransit`(일중), `sunset`(일몰)
* `moonrise`(월출), `moontransit`(월중), `moonset`(월몰)
* `civilm`, `civile` (시민박명 아침/저녁)
* `nautm`, `naute` (항해박명 아침/저녁)
* `astm`, `aste` (천문박명 아침/저녁)

---

## 5. 태양고도 정보제공 서비스 (`SrAltudeInfoService`)
특정 날짜의 지역별/위치별 시간대(9시, 12시, 15시, 18시) 태양고도, 방위각, 남중고도 정보를 제공합니다. 
(*제공 지역: 서울, 속초, 부산, 대구, 대전, 광주*)

### 5.1. 지역별 태양고도 정보조회 (`getAreaSrAltudeInfo`)
* **Req**: `locdate`(날짜, Y), `location`(지역, Y)

### 5.2. 위치별 태양고도 정보조회 (`getLCSrAltudeInfo`)
* **Req**: `locdate`(날짜, Y), `latitude`(위도, Y), `longitude`(경도, Y), `dnYn`(10진수 여부 Y/N, N)

**[공통 Response Field]**
* 위도, 경도 정보 및 10진수 정보
* `azimuth_09`, `altitude_09` (9시 방위각/고도)
* `azimuth_12`, `altitude_12` (12시 방위각/고도)
* `azimuth_15`, `altitude_15` (15시 방위각/고도)
* `azimuth_18`, `altitude_18` (18시 방위각/고도)
* `altitudeMeridian` (남중 고도)