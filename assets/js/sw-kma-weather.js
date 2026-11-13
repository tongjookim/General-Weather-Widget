/**
 * SW KMA 날씨 페이지 — 사용자 위치 자동 선택, 지점 선택 즉시 이동, 생활 예보 카드 넘기기, 전국 표 정렬
 */
(function () {
	'use strict';

	// 사용자 위치: 위치정보 동의를 받으면 "위도,경도"를 URL 에 붙여 다시 불러온다.
	// 서버가 가장 가까운 관측지점과 대기질 측정소를 고른다. (좌표는 소수 둘째 자리로 잘라 보낸다)
	document.querySelectorAll('.sw-kma').forEach(function (root) {
		var btn = root.querySelector('[data-geo-locate]');
		var msg = root.querySelector('.sw-kma__geo-msg');
		var param = root.getAttribute('data-ll-param') || 'kma_ll';
		var KEY = 'sw_kma_geo';

		if (!('geolocation' in navigator) || !window.isSecureContext) { return; }
		if (btn) { btn.hidden = false; }

		function store(v) {
			try { localStorage.setItem(KEY, v); } catch (e) { /* 저장 불가 환경 무시 */ }
		}
		function stored() {
			try { return localStorage.getItem(KEY); } catch (e) { return null; }
		}
		function say(text) {
			if (!msg) { return; }
			msg.textContent = text;
			msg.hidden = !text;
		}

		function locate(manual) {
			if (manual) { say('현재 위치를 확인하는 중입니다…'); }
			if (btn) { btn.disabled = true; }
			navigator.geolocation.getCurrentPosition(function (pos) {
				store('granted');
				var url = new URL(window.location.href);
				url.searchParams.delete('kma_stn');
				url.searchParams.set(param, pos.coords.latitude.toFixed(2) + ',' + pos.coords.longitude.toFixed(2));
				url.hash = manual ? 'sw-kma' : '';
				window.location.replace(url.toString());
			}, function (err) {
				if (btn) { btn.disabled = false; }
				if (err.code === 1) {
					store('denied');
					if (manual) { say('위치 권한이 거부되었습니다. 브라우저 설정에서 이 사이트의 위치 권한을 허용해 주세요.'); }
				} else if (manual) {
					say('현재 위치를 확인하지 못했습니다. 잠시 후 다시 시도해 주세요.');
				}
			}, { enableHighAccuracy: false, timeout: 10000, maximumAge: 10 * 60 * 1000 });
		}

		if (btn) {
			btn.addEventListener('click', function () { locate(true); });
		}

		// 지점을 고르지 않고 들어왔으면 자동으로 요청 (이전에 거부했으면 다시 묻지 않음)
		if (root.hasAttribute('data-geo-auto') && stored() !== 'denied') {
			locate(false);
		}
	});

	// 지점 선택 시 바로 이동
	document.querySelectorAll('.sw-kma [data-autosubmit]').forEach(function (sel) {
		sel.addEventListener('change', function () {
			sel.form.submit();
		});
	});

	// 그래프 가로 스크롤 시작 위치(최신 시각)는 CSS(direction: rtl)로 처리한다.

	// 생활 예보 카드: 화살표 버튼으로 한 화면씩 넘기기 (터치는 기본 가로 스크롤)
	document.querySelectorAll('.sw-kma__life').forEach(function (box) {
		var list = box.querySelector('.sw-kma__life-list');
		var btns = box.querySelectorAll('[data-life-scroll]');
		if (!list) { return; }

		function update() {
			var max = list.scrollWidth - list.clientWidth - 2;
			btns.forEach(function (b) {
				var dir = parseInt(b.getAttribute('data-life-scroll'), 10);
				b.disabled = dir < 0 ? list.scrollLeft <= 2 : list.scrollLeft >= max;
			});
		}
		btns.forEach(function (b) {
			b.addEventListener('click', function () {
				var dir = parseInt(b.getAttribute('data-life-scroll'), 10);
				list.scrollBy({ left: dir * Math.max(160, list.clientWidth * 0.8), behavior: 'smooth' });
			});
		});
		list.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update);
		update();
	});

	// 표 정렬 (data-sort="num|text", 숫자는 td[data-v] 사용)
	document.querySelectorAll('.sw-kma__table[data-sortable]').forEach(function (table) {
		var heads = table.querySelectorAll('th[data-sort]');
		heads.forEach(function (th) {
			th.setAttribute('tabindex', '0');
			th.setAttribute('role', 'button');

			function sort() {
				var idx = Array.prototype.indexOf.call(th.parentNode.children, th);
				var type = th.getAttribute('data-sort');
				// 숫자는 처음 누르면 큰 값부터
				var asc = th.getAttribute('aria-sort') === 'descending' || (!th.hasAttribute('aria-sort') && type === 'text');
				var tbody = table.tBodies[0];
				var rows = Array.prototype.slice.call(tbody.rows);

				rows.sort(function (a, b) {
					var ca = a.cells[idx], cb = b.cells[idx];
					var r;
					if (type === 'num') {
						var va = parseFloat(ca.getAttribute('data-v'));
						var vb = parseFloat(cb.getAttribute('data-v'));
						// 결측은 항상 뒤로
						if (isNaN(va)) { return 1; }
						if (isNaN(vb)) { return -1; }
						r = va - vb;
					} else {
						r = ca.textContent.trim().localeCompare(cb.textContent.trim(), 'ko');
					}
					return asc ? r : -r;
				});
				rows.forEach(function (row) { tbody.appendChild(row); });

				heads.forEach(function (h) { h.removeAttribute('aria-sort'); });
				th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');
			}

			th.addEventListener('click', sort);
			th.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					sort();
				}
			});
		});
	});
})();
