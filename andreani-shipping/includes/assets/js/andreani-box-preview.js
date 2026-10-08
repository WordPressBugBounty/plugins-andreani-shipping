/**
 * Andreani — Vista previa de cómo viaja un pedido.
 *
 * Dibuja las cajas en isométrico, acomoda lo que va en una caja de paquetería,
 * separa las cajas de un envío Bigger y evalúa si el producto entra o no en
 * paquetería. Todo en cm y kg: lo que tipea el merchant (unidad de la tienda) se
 * convierte acá, una sola vez, con los factores que llegan del servidor.
 */
(function (window) {
	'use strict';

	var COS_30 = Math.cos(Math.PI / 6);

	var THEMES = {
		kraft: { top: '#EED3A8', left: '#DDB47A', right: '#C99A5E', stroke: '#8f6a3c', opacity: 1 },
		shell: { top: '#EED3A8', left: '#DDB47A', right: '#C99A5E', stroke: '#8f6a3c', opacity: 0.28 },
		gray: { top: '#f1f2f4', left: '#dfe2e6', right: '#cfd3d9', stroke: '#9aa1aa', opacity: 1 },
		mono: { top: 'currentColor', left: 'currentColor', right: 'currentColor', stroke: 'currentColor', opacity: 0.2 }
	};

	var cfg = {
		limits: { weight: Infinity, sum_sides: Infinity, max_side: Infinity },
		aforo: 0,
		cmFactor: 1,
		kgFactor: 1,
		dimIcons: {},
		i18n: {}
	};

	function configure(options) {
		options = options || {};

		var limits = options.limits || {};

		cfg.limits = {
			weight: parseFloat(limits.weight) || Infinity,
			sum_sides: parseFloat(limits.sum_sides) || Infinity,
			max_side: parseFloat(limits.max_side) || Infinity
		};
		cfg.aforo = parseFloat(options.aforo) || 0;
		cfg.cmFactor = parseFloat(options.cm_factor) || 1;
		cfg.kgFactor = parseFloat(options.kg_factor) || 1;
		cfg.dimIcons = options.dim_icons || {};
		cfg.i18n = options.i18n || {};
	}

	function escapeHtml(value) {
		return String(value).replace(/[&<>"']/g, function (ch) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
		});
	}

	function fmt(value, decimals) {
		return (parseFloat(value) || 0).toLocaleString('es-AR', { maximumFractionDigits: decimals || 0 });
	}

	function fill(template) {
		var args = Array.prototype.slice.call(arguments, 1);

		return String(template || '')
			.replace(/%(\d)\$s/g, function () {
				return args[arguments[1] - 1];
			})
			.replace(/%s/g, function () {
				return args.length ? args.shift() : '';
			});
	}

	function toCm(value) {
		return (parseFloat(value) || 0) / cfg.cmFactor;
	}

	function toKg(value) {
		return (parseFloat(value) || 0) * cfg.kgFactor;
	}

	function project(x, y, z) {
		return [(x - y) * COS_30, (x + y) * 0.5 - z];
	}

	function points(list) {
		return list.map(function (p) {
			return p[0].toFixed(1) + ',' + p[1].toFixed(1);
		}).join(' ');
	}

	function faces(b) {
		function P(x, y, z) {
			return project(b.x + x, b.y + y, b.z + z);
		}

		var w = b.w;
		var d = b.d;
		var h = b.h;

		return {
			top: [P(0, 0, h), P(w, 0, h), P(w, d, h), P(0, d, h)],
			left: [P(0, d, 0), P(w, d, 0), P(w, d, h), P(0, d, h)],
			right: [P(w, 0, 0), P(w, d, 0), P(w, d, h), P(w, 0, h)],
			back: [[P(0, 0, 0), P(0, 0, h)], [P(0, 0, 0), P(w, 0, 0)], [P(0, 0, 0), P(0, d, 0)]],
			P: P
		};
	}

	function render(svg, boxes, options) {
		if (!svg) {
			return;
		}

		options = options || {};

		if (!boxes || !boxes.length) {
			svg.innerHTML = '';
			return;
		}

		var W = options.W || 520;
		var H = options.H || 240;

		if (options.fit) {
			var fitW = svg.clientWidth;
			var fitH = svg.clientHeight;

			if (fitW >= 40 && fitH >= 40) {
				W = fitW;
				H = fitH;
			}
		}

		var pad = options.pad === undefined ? 16 : options.pad;

		pad = Math.min(pad, W / 4, H / 4);
		var minX = 1e9;
		var minY = 1e9;
		var maxX = -1e9;
		var maxY = -1e9;

		var drawn = boxes.map(function (box) {
			var f = faces(box);

			f.top.concat(f.left, f.right).forEach(function (p) {
				minX = Math.min(minX, p[0]);
				minY = Math.min(minY, p[1]);
				maxX = Math.max(maxX, p[0]);
				maxY = Math.max(maxY, p[1]);
			});

			return { box: box, f: f };
		});

		var scale = Math.min((W - 2 * pad) / (maxX - minX || 1), (H - 2 * pad) / (maxY - minY || 1));

		if (options.max) {
			scale = Math.min(scale, options.max);
		}

		var ox = (W - (maxX - minX) * scale) / 2 - minX * scale;
		var oy = (H - (maxY - minY) * scale) / 2 - minY * scale;

		function T(list) {
			return list.map(function (p) {
				return [ox + p[0] * scale, oy + p[1] * scale];
			});
		}

		var inner = drawn.filter(function (z) {
			return z.box.t !== 'shell';
		}).sort(function (a, b) {
			return (a.box.x + a.box.y + a.box.z) - (b.box.x + b.box.y + b.box.z);
		});
		var shells = drawn.filter(function (z) {
			return z.box.t === 'shell';
		});
		var out = '';

		shells.forEach(function (z) {
			z.f.back.forEach(function (pair) {
				var seg = T(pair);

				out += '<line x1="' + seg[0][0] + '" y1="' + seg[0][1] + '" x2="' + seg[1][0] + '" y2="' + seg[1][1]
					+ '" stroke="' + THEMES.shell.stroke + '" stroke-dasharray="4 4" stroke-width="1"/>';
			});
		});

		function draw(z) {
			var box = z.box;
			var f = z.f;
			var theme = THEMES[box.t || 'kraft'];
			var shell = box.t === 'shell';
			var strokeWidth = shell ? 1.6 : 1.1;

			function polygon(list, color) {
				return '<polygon points="' + points(T(list)) + '" fill="' + color + '" fill-opacity="' + theme.opacity
					+ '" stroke="' + theme.stroke + '" stroke-width="' + strokeWidth + '"/>';
			}

			out += '<g>' + polygon(f.left, theme.left) + polygon(f.right, theme.right) + polygon(f.top, theme.top) + '</g>';

			if (box.tape) {
				var tape = T([f.P(box.w / 2, 0, box.h), f.P(box.w / 2, box.d, box.h)]);

				out += '<line x1="' + tape[0][0] + '" y1="' + tape[0][1] + '" x2="' + tape[1][0] + '" y2="' + tape[1][1]
					+ '" stroke="rgba(120,80,30,' + (shell ? 0.25 : 0.35) + ')" stroke-width="' + Math.max(2, Math.min(box.w, box.d) * scale * 0.07) + '"/>';
			}

			if (box.label) {
				var sheet = T([f.P(box.w * 0.52, box.d, box.h * 0.3), f.P(box.w * 0.9, box.d, box.h * 0.3), f.P(box.w * 0.9, box.d, box.h * 0.74), f.P(box.w * 0.52, box.d, box.h * 0.74)]);
				var band = T([f.P(box.w * 0.52, box.d, box.h * 0.64), f.P(box.w * 0.9, box.d, box.h * 0.64), f.P(box.w * 0.9, box.d, box.h * 0.74), f.P(box.w * 0.52, box.d, box.h * 0.74)]);

				out += '<polygon points="' + points(sheet) + '" style="fill:var(--andr-color-surface);stroke:var(--andr-color-border-strong)" stroke-width=".8"/>'
					+ '<polygon points="' + points(band) + '" style="fill:var(--andr-color-brand)"/>';
			}
		}

		inner.forEach(draw);
		shells.forEach(draw);

		svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
		svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
		svg.innerHTML = out;
	}

	function stacked(p) {
		return p.units > 1 && p.inc > 0;
	}

	function boxesOf(p, x, y, theme, label) {
		if (!stacked(p)) {
			return [{ x: x, y: y, z: 0, w: p.w, d: p.d, h: p.h, t: theme, tape: true, label: label }];
		}

		var boxes = [{ x: x, y: y, z: 0, w: p.w, d: p.d, h: p.base, t: theme, label: label }];

		for (var i = 1; i < p.units; i++) {
			boxes.push({ x: x, y: y, z: p.base + p.inc * (i - 1), w: p.w, d: p.d, h: p.inc, t: theme });
		}

		return boxes;
	}

	function separate(pk, theme) {
		var items = pk.slice().sort(function (a, b) {
			return (b.w * b.d) - (a.w * a.d);
		});
		var area = items.reduce(function (acc, p) { return acc + p.w * p.d; }, 0);
		var gap = Math.max(2, 0.12 * items.reduce(function (acc, p) { return acc + Math.max(p.w, p.d); }, 0) / (items.length || 1));
		var widest = items.reduce(function (acc, p) { return Math.max(acc, p.w); }, 0);
		var target = Math.max(Math.sqrt(area) * 1.3, widest);
		var x = 0;
		var y = 0;
		var rowD = 0;
		var out = [];

		items.forEach(function (p) {
			if (x > 0 && x + p.w > target) {
				y += rowD + gap;
				x = 0;
				rowD = 0;
			}

			out = out.concat(boxesOf(p, x, y, theme, true));
			x += p.w + gap;
			rowD = Math.max(rowD, p.d);
		});

		return out;
	}

	function orients(p) {
		if (stacked(p)) {
			return [[p.w, p.d, p.h], [p.d, p.w, p.h]];
		}

		var s = [p.w, p.d, p.h].sort(function (x, y) { return y - x; });
		var a = s[0];
		var b = s[1];
		var c = s[2];

		return [[a, b, c], [b, a, c], [a, c, b], [c, a, b], [b, c, a], [c, b, a]];
	}

	function shelf(items, W, D) {
		var x = 0;
		var y = 0;
		var z = 0;
		var rowD = 0;
		var layH = 0;
		var mx = 0;
		var my = 0;
		var mz = 0;
		var out = [];

		function fits(it, test) {
			for (var i = 0; i < it.or.length; i++) {
				if (test(it.or[i])) {
					return it.or[i];
				}
			}

			return null;
		}

		for (var k = 0; k < items.length; k++) {
			var it = items[k];
			var o = fits(it, function (v) { return x + v[0] <= W && y + v[1] <= D; });

			if (!o) {
				y += rowD;
				x = 0;
				rowD = 0;
				o = fits(it, function (v) { return v[0] <= W && y + v[1] <= D; });
			}

			if (!o) {
				z += layH;
				x = 0;
				y = 0;
				rowD = 0;
				layH = 0;
				o = fits(it, function (v) { return v[0] <= W && v[1] <= D; });
			}

			if (!o) {
				return null;
			}

			out.push(Object.assign({}, it.p, { w: o[0], d: o[1], h: o[2], px: x, py: y, pz: z }));
			x += o[0];
			rowD = Math.max(rowD, o[1]);
			layH = Math.max(layH, o[2]);
			mx = Math.max(mx, x);
			my = Math.max(my, y + o[1]);
			mz = Math.max(mz, z + o[2]);
		}

		return { out: out, W: mx, D: my, H: mz };
	}

	function bestPack(pk) {
		var items = pk.map(function (p) {
			return { p: p, or: orients(p) };
		}).sort(function (a, b) {
			return (b.or[0][0] * b.or[0][1] * b.or[0][2]) - (a.or[0][0] * a.or[0][1] * a.or[0][2]);
		});
		var vol = pk.reduce(function (acc, p) { return acc + p.w * p.d * p.h; }, 0);
		var side = Math.cbrt(vol);
		var cand = {};

		function add(v) {
			if (v > 0) {
				cand[v] = true;
			}
		}

		items.forEach(function (i) {
			i.or.forEach(function (o) {
				add(o[0]);
				add(o[1]);
			});
		});

		var big = items.slice(0, 3).map(function (i) { return i.or[0]; });

		for (var i = 0; i < big.length; i++) {
			for (var j = i + 1; j < big.length; j++) {
				add(big[i][0] + big[j][0]);
				add(big[i][1] + big[j][1]);
				add(big[i][0] + big[j][1]);
			}
		}

		[0.7, 0.85, 1, 1.2, 1.5, 2, 3].forEach(function (k) {
			add(side * k);
		});

		var C = Object.keys(cand).map(Number).sort(function (a, b) { return a - b; }).slice(0, 40);
		var best = null;

		C.forEach(function (W) {
			C.forEach(function (D) {
				if (D > W) {
					return;
				}

				var r = shelf(items, W, D);

				if (!r) {
					return;
				}

				var dims = [r.W + 2, r.D + 2, r.H + 1];
				var v = dims[0] * dims[1] * dims[2];
				var ok = Math.max.apply(null, dims) <= cfg.limits.max_side && dims[0] + dims[1] + dims[2] <= cfg.limits.sum_sides;
				var nonFlat = r.out.filter(function (o) { return !stacked(o) && o.h > Math.min(o.w, o.d); }).length;
				var score = v * (Math.max.apply(null, dims) / Math.min.apply(null, dims)) * Math.pow(1.15, nonFlat) * (ok ? 1 : 100);

				if (!best || score < best.score) {
					best = { out: r.out, W: r.W, D: r.D, H: r.H, v: v, score: score, ok: ok };
				}
			});
		});

		if (best) {
			best.fill = vol / best.v;
		}

		return best;
	}

	function packed(pk) {
		var oversize = totalCount(pk) > MAX_PACKED;
		var r = pk.length && !oversize ? bestPack(sample(pk, MAX_PACKED)) : null;

		if (!r) {
			return { boxes: oversize ? separate(sample(pk), 'kraft') : [], info: null };
		}

		var margin = 1.5;
		var boxes = [];

		r.out.slice(0, MAX_DRAWN).forEach(function (p) {
			boxesOf(p, margin + p.px, margin + p.py, 'kraft', false).forEach(function (b) {
				b.z += p.pz;
				boxes.push(b);
			});
		});

		boxes.push({ x: 0, y: 0, z: 0, w: r.W + 2 * margin, d: r.D + 2 * margin, h: r.H + margin, t: 'shell', tape: true, label: true });

		return {
			boxes: boxes,
			info: { w: Math.ceil(r.W + 2), d: Math.ceil(r.D + 2), h: Math.ceil(r.H + 1), fill: r.fill, ok: r.ok }
		};
	}

	var MAX_DRAWN = 60;
	var MAX_PACKED = 500;

	function countOf(p) {
		return parseInt(p.count, 10) || 1;
	}

	function totalCount(pk) {
		return pk.reduce(function (acc, p) { return acc + countOf(p); }, 0);
	}

	function sample(pk, limit) {
		limit = limit || MAX_DRAWN;

		var total = totalCount(pk);
		var groups = pk.slice(0, limit);
		var alloc = groups.map(function (p) {
			return total <= limit ? countOf(p) : Math.max(1, Math.floor(countOf(p) * limit / total));
		});
		var sum = alloc.reduce(function (acc, n) { return acc + n; }, 0);
		var out = [];

		while (sum > limit) {
			var at = alloc.indexOf(Math.max.apply(null, alloc));

			if (alloc[at] <= 1) {
				break;
			}

			alloc[at]--;
			sum--;
		}

		groups.forEach(function (p, i) {
			for (var n = 0; n < alloc[i]; n++) {
				out.push(p);
			}
		});

		return out;
	}

	function totalKg(pk) {
		return pk.reduce(function (acc, p) { return acc + p.kg * countOf(p); }, 0);
	}

	function why(pk) {
		var l = cfg.limits;
		var kg = totalKg(pk);

		if (kg > l.weight) {
			return { type: 'weight', value: kg, limit: l.weight };
		}

		for (var i = 0; i < pk.length; i++) {
			var p = pk[i];
			var longest = Math.max(p.w, p.d, p.h);

			if (longest > l.max_side) {
				return { type: 'max_side', value: longest, limit: l.max_side };
			}

			if (p.w + p.d + p.h > l.sum_sides) {
				return { type: 'sum_sides', value: p.w + p.d + p.h, limit: l.sum_sides };
			}
		}

		return null;
	}

	function reasonText(reason) {
		var key = { weight: 'why_weight', max_side: 'why_max_side', sum_sides: 'why_sum_sides' }[reason.type];

		return fill(cfg.i18n[key], fmt(reason.value, 1), fmt(reason.limit, 1));
	}

	function summary(pk) {
		var kg = totalKg(pk);
		var vol = pk.reduce(function (acc, p) { return acc + p.w * p.d * p.h * countOf(p); }, 0);
		var aforado = vol / 1e6 * cfg.aforo;

		return { kg: kg, vol: vol, aforado: aforado, charged: Math.max(kg, aforado) };
	}

	function positive(value) {
		return (parseFloat(String(value).replace(',', '.')) || 0) > 0;
	}

	function bultoComplete(b) {
		return positive(b.weight) && positive(b.width) && positive(b.height) && positive(b.depth);
	}

	function completeBultos(list) {
		return (list || []).filter(bultoComplete);
	}

	function ignoredHint(list) {
		var numbers = [];

		(list || []).forEach(function (b, i) {
			if (!bultoComplete(b)) {
				numbers.push(i + 2);
			}
		});

		if (!numbers.length) {
			return '';
		}

		return numbers.length === 1
			? fill(cfg.i18n.piece_ignored, numbers[0])
			: fill(cfg.i18n.piece_ignored_many, numbers.join(', '));
	}

	function productComplete(draft) {
		return positive(draft.weight) && positive(draft.length) && positive(draft.width) && positive(draft.height);
	}

	function productPackages(p) {
		var n = 1;
		var w = toCm(p.width);
		var d = toCm(p.length);
		var h = toCm(p.height);
		var kg = toKg(p.weight);

		if (p.mode === 'apilado' && p.apilado) {
			n = p.apilado.maxUnits;
			w += p.apilado.incW * (n - 1);
			d += p.apilado.incD * (n - 1);
			h += p.apilado.incH * (n - 1);
			kg *= n;
		}

		var pk = [{ w: w, d: d, h: h, kg: kg, units: 1, base: h, inc: 0 }];

		if (p.mode === 'multibulto') {
			completeBultos(p.bultos).forEach(function (b) {
				pk.push({ w: toCm(b.width), d: toCm(b.depth), h: toCm(b.height), kg: toKg(b.weight), units: 1, base: toCm(b.height), inc: 0 });
			});
		}

		return pk;
	}

	function evaluateProduct(p) {
		var missing = !(p.weight > 0 && p.width > 0 && p.height > 0 && p.length > 0);

		return { missing: missing, reason: missing ? null : why(productPackages(p)) };
	}

	function applyBadge($badge, evaluation, labels) {
		var state = evaluation.missing ? 'missing' : (evaluation.reason ? 'bigger' : 'ok');
		var modifier = { missing: 'warning', bigger: 'info', ok: 'success' }[state];
		var texts = labels || { missing: cfg.i18n.status_missing, bigger: cfg.i18n.status_bigger, ok: cfg.i18n.status_ok };
		var label = texts[state];
		var $text = $badge.find('[data-andr="badge-text"]');

		$badge
			.removeClass('andr-badge--warning andr-badge--info andr-badge--success')
			.addClass('andr-badge--' + modifier);

		($text.length ? $text : $badge).text(label || '');
	}

	function drawPackage(p) {
		return {
			w: parseFloat(p.w) || 0,
			d: parseFloat(p.d) || 0,
			h: parseFloat(p.h) || 0,
			kg: parseFloat(p.kg) || 0,
			units: parseInt(p.units, 10) || 1,
			base: parseFloat(p.base) || 0,
			inc: parseFloat(p.inc) || 0,
			count: countOf(p),
			name: p.name ? String(p.name) : '',
			ref: p.ref ? String(p.ref) : ''
		};
	}

	function dims(p) {
		return fmt(p.d, 1) + ' × ' + fmt(p.w, 1) + ' × ' + fmt(p.h, 1);
	}

	function weightText(kg) {
		return kg < 1 ? fmt(kg * 1000) + ' g' : fmt(kg, 1) + ' kg';
	}

	function heterogeneous(pk) {
		var sides = pk.map(function (p) {
			return Math.max(p.w, p.d, p.h);
		});
		var smallest = Math.min.apply(null, sides);

		return pk.length > 1 && smallest > 0 && Math.max.apply(null, sides) > 3 * smallest;
	}

	function boxList(ul, pk, labels) {
		var offset = 0;

		ul.innerHTML = pk.map(function (p) {
			var count = countOf(p);
			var head = count > 1
				? [fmt(count) + ' × ' + (p.name || labels.box), p.ref]
				: [labels.box + ' ' + (offset + 1), p.name, p.ref];
			var parts = head.concat([
				p.units > 1 ? fill(labels.units, p.units) : '',
				dims(p) + ' cm',
				weightText(p.kg)
			]).filter(Boolean);

			offset += count;

			return '<li class="andr-boxlist__item"><svg class="andr-boxlist__thumb" role="img" aria-hidden="true"></svg>'
				+ '<span>' + escapeHtml(parts.join(' · ')) + '</span></li>';
		}).join('');

		Array.prototype.forEach.call(ul.querySelectorAll('svg'), function (svg, i) {
			render(svg, boxesOf(pk[i], 0, 0, 'kraft', false), { W: 48, H: 40, pad: 3 });
		});
	}

	function showBoxList(svg, pk) {
		var stage = svg.parentNode;
		var host = stage.parentNode.querySelector('[data-andr="boxlist"]');

		if (!pk) {
			if (host) {
				host.hidden = true;
			}

			stage.style.display = '';
			return;
		}

		if (!host) {
			host = document.createElement('div');
			host.setAttribute('data-andr', 'boxlist');
			host.innerHTML = '<p class="andr-boxlist__title"></p><ul class="andr-boxlist"></ul>';
			stage.parentNode.insertBefore(host, stage.nextSibling);
		}

		host.querySelector('.andr-boxlist__title').textContent = fill(cfg.i18n.list_title, fmt(totalCount(pk)));
		boxList(host.querySelector('ul'), pk, { box: cfg.i18n.box_word, units: cfg.i18n.box_units });
		host.hidden = false;
		stage.style.display = 'none';
	}

	var STAGE = { W: 420, H: 230, pad: 16, fit: true };

	function resultClasses(modifier) {
		return 'andr-dispatch__result andr-dispatch__result--' + modifier;
	}

	function tooBigHtml(info) {
		var s = cfg.i18n;
		var longest = Math.max(info.w, info.d, info.h);
		var over = longest > cfg.limits.max_side
			? fill(s.pack_side_over, longest, fmt(cfg.limits.max_side))
			: fill(s.pack_sum_over, info.w + info.d + info.h, fmt(cfg.limits.sum_sides));

		return '<div class="andr-dispatch__warnbox"><b>' + escapeHtml(s.pack_too_big) + '</b> '
			+ escapeHtml(fill(s.pack_too_big_detail, info.w, info.d, info.h, over)) + '</div>';
	}

	function packResult(pk, quantity, mode, stageSvg, resultEl, cart) {
		var s = cfg.i18n;
		var pack = packed(pk);
		var info = pack.info;
		var sum = summary(pk);
		var html = '<span class="andr-dispatch__result-title">' + escapeHtml(s.pack_title) + '</span>'
			+ escapeHtml(cart ? s.cart_pack_together : (quantity > 1 ? fill(s.pack_together, quantity) : s.pack_together_one))
			+ (!cart && mode === 'apilado' && quantity > 1 ? ' ' + escapeHtml(s.pack_stacked) : '');

		render(stageSvg, pack.boxes, STAGE);

		if (info && info.ok) {
			html += '<span class="andr-dispatch__note">' + escapeHtml(fill(s.pack_hint, info.w, info.d, info.h)) + '</span>';
		} else if (info) {
			html += tooBigHtml(info);
		}

		html += '<span class="andr-dispatch__note">' + escapeHtml(fill(s.pack_charged, fmt(sum.charged, 1))) + '</span>';

		resultEl.className = resultClasses('ok');
		resultEl.innerHTML = html;
	}

	function biggerResult(pk, reason, mode, stageSvg, resultEl, cart, mixed, list) {
		var s = cfg.i18n;
		var count = totalCount(pk);
		var title = count === 1 ? s.big_title_one : fill(s.big_title_many, fmt(count), fmt(count));
		var note = cart ? [] : [s.big_home_only];

		if (cart) {
			title += ' · ' + s.big_home_only.toLowerCase();

			if (mixed) {
				note.push(s.cart_big_mixed);
			}
		} else if (mode === 'apilado' && pk.some(stacked)) {
			note.push(s.big_per_pile);
		}

		if (list && heterogeneous(pk)) {
			showBoxList(stageSvg, pk);
		} else {
			showBoxList(stageSvg, null);
			render(stageSvg, separate(sample(pk), 'kraft'), STAGE);
		}

		resultEl.className = resultClasses('big');
		resultEl.innerHTML = '<span class="andr-dispatch__result-title">' + escapeHtml(title) + '</span>'
			+ escapeHtml(fill(s.big_because, reasonText(reason)))
			+ (note.length ? '<span class="andr-dispatch__note">' + escapeHtml(note.join(' · ')) + '</span>' : '');
	}

	function paint(options) {
		var pk = (options.packages || []).map(drawPackage);

		if (options.list) {
			showBoxList(options.svg, null);
		}

		if (!pk.length) {
			render(options.svg, []);
			options.result.className = resultClasses('empty');
			options.result.textContent = options.emptyText || cfg.i18n.preview_empty || '';
			return;
		}

		var reason = why(pk);

		if (reason) {
			biggerResult(pk, reason, options.mode, options.svg, options.result, options.cart, options.mixed, options.list);
		} else {
			packResult(pk, options.quantity, options.mode, options.svg, options.result, options.cart);
		}
	}

	function createPreview(options) {
		var $ = window.jQuery;
		var $root = $(options.root);
		var $qty = $root.find('[data-andr="qty"]');
		var $qtyInput = $root.find('[data-andr="qty-input"]');
		var $qtyUnit = $root.find('[data-andr="qty-unit"]');
		var $qtyCap = $root.find('[data-andr="qty-cap"]');
		var $detail = $root.find('[data-andr="detail"]');
		var svg = $root.find('[data-andr="stage"]').get(0);
		var result = $root.find('[data-andr="result"]').get(0);
		var $result = $(result);
		var timer = null;
		var request = 0;
		var cache = {};
		var cached = 0;
		var qtyTimer = null;
		var current = parseInt($qtyInput.val(), 10) || 1;

		function quantity() {
			return current;
		}

		function validQuantity(value) {
			return value >= 1 && value <= 99;
		}

		function applyQuantity(q) {
			current = q;
			$qty.val(Math.min(q, parseInt($qty.attr('max'), 10)));
			$qtyCap.toggleClass('is-on', q > parseInt($qty.attr('max'), 10));
			$qtyUnit.text(q === 1 ? cfg.i18n.preview_unit_one : cfg.i18n.preview_unit_many);
			refresh();
		}

		function showEmpty() {
			request++;
			clearTimeout(timer);
			AndreaniLoader.hide($result);
			paint({ svg: svg, result: result, packages: [] });
			$detail.html('<p class="andr-dispatch__message">' + escapeHtml(cfg.i18n.preview_empty || '') + '</p>');
		}

		function show(entry) {
			var q = quantity();
			var packages = entry.byQuantity[q] || [];

			paint({ svg: svg, result: result, packages: packages, quantity: q, mode: entry.mode });

			var hint = packages.length && options.hint ? options.hint() : '';

			if (hint) {
				result.insertAdjacentHTML('beforeend', '<span class="andr-dispatch__note">' + escapeHtml(hint) + '</span>');
			}

			$detail.html(entry.html);
		}

		function refresh() {
			clearTimeout(timer);

			var draft = options.getDraft();

			if (!productComplete(draft)) {
				showEmpty();
				return;
			}

			var key = JSON.stringify(draft);
			var q = quantity();

			if (cache[key] && cache[key].byQuantity[q]) {
				request++;
				AndreaniLoader.hide($result);
				show(cache[key]);
				return;
			}

			timer = setTimeout(function () {
				var id = ++request;

				AndreaniLoader.show($result, { size: 'sm', text: cfg.i18n.loader_preview, textAfter: 400 });

				$.post(options.ajaxUrl, $.extend({
					action: 'andreani_preview_bultos',
					nonce: options.nonce,
					quantity: q,
					max_quantity: cache[key] ? 0 : $qty.attr('max')
				}, draft)).done(function (res) {
					if (id !== request) {
						return;
					}

					AndreaniLoader.hide($result, function () {
						if (id !== request) {
							return;
						}

						if (!res || !res.success || !res.data) {
							showEmpty();
							return;
						}

						if (!cache[key]) {
							if (cached >= 30) {
								cache = {};
								cached = 0;
							}

							cache[key] = { byQuantity: {}, html: res.data.html, mode: draft.dispatch_mode };
							cached++;
						}

						$.extend(cache[key].byQuantity, res.data.packages_by_quantity || {});
						cache[key].byQuantity[q] = res.data.packages || [];
						show(cache[key]);
					});
				}).fail(function () {
					if (id === request) {
						AndreaniLoader.hide($result, showEmpty);
					}
				});
			}, 250);
		}

		$qty.on('input change', function () {
			var q = parseInt($qty.val(), 10) || 1;

			$qtyInput.val(q);
			applyQuantity(q);
		});

		$qtyInput.on('input', function () {
			var q = parseInt($qtyInput.val(), 10);

			clearTimeout(qtyTimer);

			if (!validQuantity(q)) {
				return;
			}

			qtyTimer = setTimeout(function () {
				applyQuantity(q);
			}, 250);
		});

		$qtyInput.on('change blur', function () {
			var q = parseInt($qtyInput.val(), 10);

			clearTimeout(qtyTimer);

			if (!validQuantity(q)) {
				$qtyInput.val(current);
				return;
			}

			$qtyInput.val(q);

			if (q !== current) {
				applyQuantity(q);
			}
		});

		return { refresh: refresh };
	}

	var ART = {
		una: function () {
			return [{ x: 0, y: 0, z: 0, w: 30, d: 30, h: 30, tape: true }];
		},
		caja: function () {
			return [{ x: 0, y: 0, z: 0, w: 30, d: 30, h: 30, tape: true }, { x: 36, y: 0, z: 0, w: 30, d: 30, h: 30, tape: true }];
		},
		apilado: function () {
			return [{ x: 0, y: 0, z: 0, w: 30, d: 30, h: 30 }, { x: 0, y: 0, z: 30, w: 30, d: 30, h: 9 }, { x: 0, y: 0, z: 39, w: 30, d: 30, h: 9 }];
		},
		piezas: function () {
			return [{ x: 0, y: 0, z: 0, w: 34, d: 30, h: 30, tape: true }, { x: 40, y: 0, z: 0, w: 26, d: 22, h: 20, tape: true }];
		}
	};

	function mountIcons(root) {
		Array.prototype.forEach.call((root || document).querySelectorAll('[data-andr-icon]'), function (svg) {
			var draw = ART[svg.getAttribute('data-andr-icon')];

			if (draw) {
				render(svg, draw().map(function (b) {
					return Object.assign({}, b, { tape: false, t: 'mono' });
				}), { W: 28, H: 28, pad: 1 });
			}
		});
	}

	function mountArt(root) {
		Array.prototype.forEach.call((root || document).querySelectorAll('[data-andr-art]'), function (svg) {
			var draw = ART[svg.getAttribute('data-andr-art')];

			if (draw) {
				render(svg, draw(), { W: 64, H: 44, pad: 2 });
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			mountArt();
		});
	} else {
		mountArt();
	}

	function dimField(options) {
		var attrs = ' class="' + escapeHtml(options.cls) + '" min="0" step="' + escapeHtml(options.step) + '" value="' + escapeHtml(options.value) + '"';

		return '<label class="andr-dim">'
			+ '<span class="andr-dim__label">' + escapeHtml(options.label) + '</span>'
			+ '<span class="andr-dim__box">' + (cfg.dimIcons[options.key] || '') + '<input type="number"' + attrs + '><span class="andr-dim__unit">' + escapeHtml(options.unit) + '</span></span>'
			+ '</label>';
	}

	function mountThumbs(root) {
		Array.prototype.forEach.call((root || document).querySelectorAll('[data-andr-thumb]'), function (svg) {
			var packages;

			try {
				packages = JSON.parse(svg.getAttribute('data-andr-thumb'));
			} catch (e) {
				packages = [];
			}

			var boxes = packages && packages.length
				? separate(sample(packages.map(drawPackage)), 'kraft').map(function (b) {
					return Object.assign({}, b, { label: false });
				})
				: [{ x: 0, y: 0, z: 0, w: 30, d: 30, h: 30, t: 'gray' }];

			render(svg, boxes, { W: 48, H: 48, pad: 3 });
		});
	}

	window.AndreaniBoxPreview = {
		configure: configure,
		render: render,
		boxesOf: boxesOf,
		separate: separate,
		sample: sample,
		totalCount: totalCount,
		boxList: boxList,
		heterogeneous: heterogeneous,
		dims: dims,
		weightText: weightText,
		packed: packed,
		why: why,
		reasonText: reasonText,
		summary: summary,
		fmt: fmt,
		fill: fill,
		tooBigHtml: tooBigHtml,
		evaluateProduct: evaluateProduct,
		completeBultos: completeBultos,
		ignoredHint: ignoredHint,
		applyBadge: applyBadge,
		paint: paint,
		createPreview: createPreview,
		mountIcons: mountIcons,
		mountArt: mountArt,
		dimField: dimField,
		mountThumbs: mountThumbs
	};
})(window);
