/**
 * SW KASI Sync - 날짜 이동(이전/오늘/다음) AJAX
 */
(function () {
	'use strict';

	if (typeof window.SW_KSASI === 'undefined') {
		return;
	}
	var cfg = window.SW_KSASI;

	function pad(n) {
		return (n < 10 ? '0' : '') + n;
	}

	function shiftDate(ymd, days) {
		var d = new Date(+ymd.slice(0, 4), +ymd.slice(4, 6) - 1, +ymd.slice(6, 8));
		d.setDate(d.getDate() + days);
		return '' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate());
	}

	function load(widget, date) {
		if (widget.classList.contains('is-loading')) {
			return;
		}
		widget.classList.add('is-loading');

		var body = new URLSearchParams();
		body.append('action', cfg.action);
		body.append('atts', widget.getAttribute('data-atts'));
		body.append('sig', widget.getAttribute('data-sig'));
		body.append('date', date);

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (!json || !json.success) {
					throw new Error(json && json.data && json.data.message ? json.data.message : cfg.error);
				}
				widget.innerHTML = json.data.html;
				widget.setAttribute('data-date', json.data.date);
			})
			.catch(function (err) {
				window.alert(err && err.message ? err.message : cfg.error);
			})
			.then(function () {
				widget.classList.remove('is-loading');
			});
	}

	/* 해 ↔ 달 카드 전환: 카드 아무 곳이나 클릭(터치)하거나 전환 버튼(키보드)을 누르면 바뀐다. */
	function toggleView(widget, card, fromKeyboard) {
		var next = card.classList.contains('sw-ksasi__card--sun') ? 'moon' : 'sun';
		// data-view 는 바깥 컨테이너에 두므로 날짜 이동(AJAX)으로 내용이 바뀌어도 유지된다.
		widget.setAttribute('data-view', next);
		if (fromKeyboard) {
			var target = widget.querySelector('.sw-ksasi__card--' + next + ' .sw-ksasi__switch');
			if (target) {
				target.focus();
			}
		}
	}

	document.addEventListener('click', function (e) {
		if (!e.target.closest) {
			return;
		}
		var card = e.target.closest('.sw-ksasi--flip .sw-ksasi__card');
		if (card && !e.target.closest('a')) {
			var sw = e.target.closest('.sw-ksasi__switch');
			// detail === 0 이면 키보드(Enter/Space)로 버튼을 누른 경우
			toggleView(card.closest('.sw-ksasi'), card, !!(sw && e.detail === 0));
			return;
		}

		var btn = e.target.closest('.sw-ksasi__btn');
		if (!btn) {
			return;
		}
		var widget = btn.closest('.sw-ksasi');
		if (!widget) {
			return;
		}
		e.preventDefault();

		var shift = parseInt(btn.getAttribute('data-shift'), 10) || 0;
		var date;
		if (shift === 0) {
			date = ''; // 빈 값이면 서버(사이트 시간대) 기준 오늘
		} else {
			date = shiftDate(widget.getAttribute('data-date'), shift);
		}
		load(widget, date);
	});
})();
