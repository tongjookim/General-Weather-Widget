# 기상청 API허브 지상관측 API 명세 (요약)

원문: https://apihub.kma.go.kr/apiList.do?seqApi=2

## 공통
* **Host**: `https://apihub.kma.go.kr`
* **인증**: API허브에서 발급한 인증키 → 요청 파라미터 `authKey` (공공데이터포털 ServiceKey 와 별개)
* **시각**: KST. 시간자료 `YYYYMMDDHHMI`, 일자료 `YYYYMMDD`
* **지점**: `stn` 에 지점번호, 여러 개는 `:` 로 구분, `0`/생략 시 전체 지점
* **typ01 응답**: 텍스트. `#` 로 시작하는 주석(헤더·도움말) + 공백 구분 데이터 행. `help=1` 이면 컬럼 설명(`#  N. NAME : 설명`)이 붙는다.
* **인증 실패**: HTTP 401 + JSON `{"result":{"status":401,"message":"..."}}`
* **결측**: -9, -9.0, -99.0 등 음수 표기

## 1. 지상 관측자료 (`/api/typ01/url/`)

### 1.1 시간자료 `kma_sfctm2.php`
| 파라미터 | 설명 | 예 |
|---|---|---|
| tm | 관측시각(KST), 없으면 현재 | 202211300900 |
| stn | 지점번호, 0=전체 | 0 |
| help | 1=도움말 | 1 |
| authKey | 인증키 (필수) | |

응답: TM, STN, WD(풍향, 36방위), WS(m/s), GST_WD, GST_WS, GST_TM, PA(현지기압 hPa), PS(해면기압 hPa), PT, PR, TA(기온 ℃), TD(이슬점 ℃), HM(습도 %), PV(hPa), RN(강수량 mm), RN_DAY(일강수 mm), RN_JUN, RN_INT(mm/h), SD_HR3, SD_DAY, SD_TOT(적설 cm), WC(현재일기 Code 4677), WP, WW, CA_TOT(전운량 1/10), CA_MID, CH_MIN(최저운고 100m), CT, CT_TOP, CT_MID, CT_LOW, VS(시정 10m), SS(일조 hr), SI(일사 MJ/m²), ST_GD, TS(지면온도 ℃), TE_005~TE_03(지중온도 ℃), ST_SEA, WH(파고 m), BF, IR, IX

### 1.2 시간자료 기간 `kma_sfctm3.php`
`tm1`, `tm2`(최대 31일), `stn`, `help`, `authKey`. 응답 필드는 1.1과 동일.

### 1.3 일자료 `kma_sfcdd.php`
`tm`(YYYYMMDD), `stn`, `disp`(0=CSV, 1=고정폭), `help`, `authKey`

응답: TM, STN, WS_AVG, WR_DAY, WD_MAX, WS_MAX, WS_MAX_TM, WD_INS, WS_INS, WS_INS_TM, TA_AVG, TA_MAX, TA_MAX_TM, TA_MIN, TA_MIN_TM, TD_AVG, TS_AVG, TG_MIN, HM_AVG, HM_MIN, HM_MIN_TM, PV_AVG, EV_S, EV_L, FG_DUR, PA_AVG, PS_AVG, PS_MAX, PS_MAX_TM, PS_MIN, PS_MIN_TM, CA_TOT, SS_DAY, SS_DUR, SS_CMB, SI_DAY, SI_60M_MAX, SI_60M_MAX_TM, RN_DAY, RN_D99, RN_DUR, RN_60M_MAX, RN_60M_MAX_TM, RN_10M_MAX, RN_10M_MAX_TM, RN_POW_MAX, RN_POW_MAX_TM, SD_NEW, SD_NEW_TM, SD_MAX, SD_MAX_TM, TE_05, TE_10, TE_15, TE_30, TE_50

### 1.4 일자료 기간 `kma_sfcdd3.php`
`tm1`, `tm2`, `stn`, `help`, `authKey` (+ `obs`, `mode`). 응답 필드는 1.3과 동일.

### 1.5 요소별 `kma_sfctm5.php`
`tm1`, `tm2`, `obs`(TA, TD, HM, PV, PA, PS, CA_TOT, CA_MID, CH_MIN, CT, VS, TS), `stn`, `disp`, `help`, `authKey`
응답: TM, STN, LON, LAT, HT, VAL

## 2. 평년값 `sfc_norm1.php`
`norm`(D=일, S=순, M=월, Y=연), `tmst`(2021 = 1991~2020 평년), `stn`, `MM1`, `DD1`, `MM2`, `DD2`, `authKey`

응답: ST, STN, MM, DD, TA, TA_MAX, TA_MIN, RN, RN_DUR, EV, WS, HM, PV, SS, SI, FG_DUR, CA_TOT, PA, PS, TG_MIN, TS, TE_*, RD_01, RD_10, WD_DN, NS_DN

## 3. 지상기상연보 `/api/typ02/openApi/SfcYearlyInfoService/{getYearSumry|getYearSumry2|getAvgTaAnamaly|getRnAnamaly|getStnPhnmnData|getStnPhnmnData2|getStnPhnmnData3}`
`pageNo`, `numOfRows`, `dataType`(XML/JSON), `year`, (`station`), `authKey`

## 4. 지상기상월보 `/api/typ02/openApi/SfcMtlyInfoService/{getNote|getSfcStnLstTbl|getMmSumry|getMmSumry2|getDailyWthrData}`
`pageNo`, `numOfRows`, `dataType`, `year`, `month`, (`station`), `authKey`

## 5. 지상기상현상 그래픽 `/api/typ03/php/alw/sfc/sfc_ww_pnt.php`
`obs=ww_sfc`, `tm`, `stn`, `map`, `legend`, `size`, `itv`, `authKey` → 이미지

---

## 이 플러그인에서 사용하는 API (`[sw_kma_weather]` 페이지)
| 섹션 | API | 캐시 |
|---|---|---|
| 현재 날씨 + 최근 24시간 그래프 | `kma_sfctm3.php` (선택 지점, 최근 24시간) | 10분 |
| 전국 관측 현황 표 | `kma_sfctm2.php` (stn=0, 최신 정시) | 10분 |
| 최근 7일 일자료 | `kma_sfcdd3.php` (어제까지 7일) | 3시간 |
| 오늘 평년값 비교 | `sfc_norm1.php` (norm=D, tmst=2021) | 30일 |
