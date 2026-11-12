/**
 * SW KMA 날씨 페이지 — 지점 선택 즉시 이동, 전국 표 정렬
 */
(function () {
	'use strict';

	// 지점 선택 시 바로 이동
	document.querySelectorAll('.sw-kma [data-autosubmit]').forEach(function (sel) {
		sel.addEventListener('change', function () {
			sel.form.submit();
		});
	});

	// 그래프 가로 스크롤 시작 위치(최신 시각)는 CSS(direction: rtl)로 처리한다.

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
