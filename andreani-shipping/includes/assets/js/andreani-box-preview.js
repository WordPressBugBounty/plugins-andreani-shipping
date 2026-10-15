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
		bag: { top: '#ffffff', left: '#f3f4f6', right: '#d9dce1', stroke: '#8d949e', opacity: 1 },
		gray: { top: '#f1f2f4', left: '#dfe2e6', right: '#cfd3d9', stroke: '#9aa1aa', opacity: 1 },
		mono: { top: 'currentColor', left: 'currentColor', right: 'currentColor', stroke: 'currentColor', opacity: 0.2 }
	};

	var cfg = {
		limits: { weight: Infinity, sum_sides: Infinity, max_side: Infinity },
		aforo: 0,
		cmFactor: 1,
		kgFactor: 1,
		dimIcons: {},
		serviceIcons: {},
		storeBoxes: [],
		boxesUrl: '',
		overflowSupported: true,
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
		cfg.serviceIcons = options.service_icons || {};
		cfg.storeBoxes = options.store_boxes || [];
		cfg.packages = options.packages || [];
		cfg.packageApi = options.package_api || {};
		cfg.boxesUrl = options.boxes_url || '';
		cfg.overflowSupported = options.overflow_supported !== false;
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

	function faceMap(origin, uEnd, vEnd) {
		var u = [uEnd[0] - origin[0], uEnd[1] - origin[1]];
		var v = [vEnd[0] - origin[0], vEnd[1] - origin[1]];

		return function (a, b) {
			return [origin[0] + a * u[0] + b * v[0], origin[1] + a * u[1] + b * v[1]];
		};
	}

	var QR_MODULES = ['11011', '11011', '00100', '11010', '11101'];
	var BARS = [2, 1, 1, 3, 1, 2, 2, 1, 3, 1, 1, 2, 1, 3, 2, 1, 1, 2, 3, 1, 2, 1, 1, 3, 1, 2, 2, 1];

	function dist(a, b) {
		return Math.sqrt(Math.pow(b[0] - a[0], 2) + Math.pow(b[1] - a[1], 2));
	}

	function labelMarkup(map) {
		function quad(u0, v0, u1, v1) {
			return 'M' + points([map(u0, v0), map(u1, v0), map(u1, v1), map(u0, v1)]).replace(/ /g, 'L') + 'Z';
		}

		function shape(d, attrs) {
			return '<path d="' + d + '" ' + attrs + '/>';
		}

		function qr(u, v, qa, qb) {
			var d = '';

			QR_MODULES.forEach(function (row, r) {
				row.split('').forEach(function (cell, c) {
					if (cell === '1') {
						d += quad(u + qa * c / 5, v + qb * (4 - r) / 5, u + qa * (c + 1) / 5, v + qb * (5 - r) / 5);
					}
				});
			});

			return shape(d, 'fill="#1f2328"');
		}

		var o = map(0, 0);
		var pw = dist(o, map(1, 0));
		var ph = dist(o, map(0, 1));
		var iso = window.AndreaniIsotipo;
		var out = shape(quad(0, 0, 1, 1), 'style="fill:var(--andr-color-surface);stroke:var(--andr-color-border-strong)" stroke-width=".8"')
			+ shape(quad(0, 0.8, 1, 1), 'fill="#15181c"');

		if (iso && iso.d && ph * 0.2 >= 5) {
			var bb = isotipoBox(iso.d);
			var fb = 0.12;
			var fa = Math.min(0.5, fb * ph / (pw * bb.height / bb.width));

			out += isotipoMarkup(map, 0.08, 0.9 - fb / 2, fa, fb, '#fff');
		}

		if (Math.min(pw, ph) < 24) {
			return out + shape(quad(0.12, 0.2, 0.88, 0.66), 'fill="#a0a6ac"');
		}

		var qb = 0.16;
		var qa = Math.min(0.4, qb * ph / pw);
		var d = '';
		var total = BARS.reduce(function (acc, n) { return acc + n; }, 0);
		var x = 0;

		BARS.forEach(function (n, i) {
			if (i % 2 === 0) {
				d += quad(0.1 + 0.8 * x / total, 0.4, 0.1 + 0.8 * (x + n) / total, 0.54);
			}

			x += n;
		});

		return out + qr(0.08, 0.6, qa, qb) + qr(0.92 - qa, 0.6, qa, qb)
			+ shape(d, 'fill="#1f2328"')
			+ qr(0.08, 0.12, qa, qb) + qr(0.92 - qa, 0.12, qa, qb);
	}

	function renderLabels(svg, count, W, H) {
		var h = H - 6;
		var w = h * 0.87;
		var gap = 8;
		var x = (W - (count * w + (count - 1) * gap)) / 2;
		var out = '';

		for (var i = 0; i < count; i++) {
			out += labelMarkup(faceMap([x, 3 + h], [x + w, 3 + h], [x, 3]));
			x += w + gap;
		}

		svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
		svg.innerHTML = out;
	}

	var isoBox = null;

	function isotipoBox(d) {
		if (!isoBox) {
			var ns = 'http://www.w3.org/2000/svg';
			var probe = document.createElementNS(ns, 'svg');
			var path = document.createElementNS(ns, 'path');

			probe.setAttribute('style', 'position:absolute;width:0;height:0;visibility:hidden');
			path.setAttribute('d', d);
			probe.appendChild(path);
			document.body.appendChild(probe);
			isoBox = path.getBBox();
			document.body.removeChild(probe);
		}

		return isoBox;
	}

	function isotipoMarkup(map, a, b, fa, fb, fill) {
		var iso = window.AndreaniIsotipo;

		if (!iso || !iso.d) {
			return '';
		}

		var bb = isotipoBox(iso.d);
		var o = map(0, 0);
		var pu = map(1, 0);
		var pv = map(0, 1);
		var origin = map(a - bb.x * fa / bb.width, b - bb.y * fb / bb.height);
		var m = [
			(pu[0] - o[0]) * fa / bb.width, (pu[1] - o[1]) * fa / bb.width,
			(pv[0] - o[0]) * fb / bb.height, (pv[1] - o[1]) * fb / bb.height,
			origin[0], origin[1]
		].map(function (n) {
			return n.toFixed(5);
		}).join(' ');

		return '<path d="' + iso.d + '" transform="matrix(' + m + ')" fill="' + fill + '"/>';
	}

	function area(list) {
		var sum = 0;

		list.forEach(function (p, i) {
			var n = list[(i + 1) % list.length];

			sum += p[0] * n[1] - n[0] * p[1];
		});

		return Math.abs(sum) / 2;
	}

	function boxLabelMarkup(box, f, T, onTop) {
		var P = f.P;
		var cands = [
			{ pts: [P(0, box.d, 0), P(box.w, box.d, 0), P(0, box.d, box.h)], quad: f.left, a: box.w, b: box.h },
			{ pts: [P(box.w, box.d, 0), P(box.w, 0, 0), P(box.w, box.d, box.h)], quad: f.right, a: box.d, b: box.h },
			{ pts: [P(0, box.d, box.h), P(box.w, box.d, box.h), P(0, 0, box.h)], quad: f.top, a: box.w, b: box.d }
		].map(function (c) {
			c.area = area(T(c.quad));

			return c;
		});
		var best = onTop ? cands[2] : cands.reduce(function (acc, c) {
			return c.area > acc.area ? c : acc;
		});
		var lw = Math.min(0.38 * best.a, 0.44 * best.b * 0.87);
		var fa = lw / best.a;
		var fb = lw / 0.87 / best.b;
		var o = T(best.pts);
		var F = faceMap(o[0], o[1], o[2]);
		var sheet = [F(0.5 - fa / 2, 0.5 - fb / 2), F(0.5 + fa / 2, 0.5 - fb / 2), F(0.5 - fa / 2, 0.5 + fb / 2)];

		return labelMarkup(faceMap(sheet[0], sheet[1], sheet[2]));
	}

	function bagMarkup(z, T) {
		var b = z.box;
		var P = z.f.P;
		var o = T([P(0, b.d, 0), P(b.w, b.d, 0), P(0, b.d, b.h), P(b.w, 0, 0)]);
		var F = faceMap(o[0], o[1], o[2]);
		var dv = [o[3][0] - o[1][0], o[3][1] - o[1][1]];
		var stroke = THEMES.bag.stroke;

		function pt(u, v, back) {
			var p = F(u, v);

			return (p[0] + (back ? dv[0] : 0)).toFixed(1) + ',' + (p[1] + (back ? dv[1] : 0)).toFixed(1);
		}

		function shape(d, attrs) {
			return '<path d="' + d + '" ' + attrs + '/>';
		}

		var body = 'M' + pt(0.035, 0.08) + 'Q' + pt(-0.02, 0.5) + ' ' + pt(0.035, 0.92)
			+ 'Q' + pt(0.5, 1) + ' ' + pt(0.965, 0.92)
			+ 'Q' + pt(1.02, 0.5) + ' ' + pt(0.965, 0.08)
			+ 'Q' + pt(0.965, 0) + ' ' + pt(0.9, 0)
			+ 'L' + pt(0.1, 0)
			+ 'Q' + pt(0.035, 0) + ' ' + pt(0.035, 0.08) + 'Z';
		var side = 'M' + pt(0.965, 0.08) + 'Q' + pt(1.02, 0.5) + ' ' + pt(0.965, 0.92)
			+ 'L' + pt(0.965, 0.92, true) + 'Q' + pt(1.02, 0.5, true) + ' ' + pt(0.965, 0.08, true) + 'Z';
		var mouth = 'M' + pt(0.035, 0.92) + 'Q' + pt(0.5, 1) + ' ' + pt(0.965, 0.92) + 'Q' + pt(0.5, 0.84) + ' ' + pt(0.035, 0.92) + 'Z';
		var out = shape(side, 'fill="' + THEMES.bag.right + '" stroke="' + stroke + '" stroke-width="0.9"')
			+ shape(body, 'fill="' + THEMES.bag.left + '" stroke="' + stroke + '" stroke-width="0.9"')
			+ shape(mouth, 'fill="#aeb4bc" stroke="' + stroke + '" stroke-width="0.9"')
			+ shape('M' + pt(0.1, 0.07) + 'L' + pt(0.9, 0.07), 'fill="none" stroke="#b4bac2" stroke-width="1"')
			+ shape('M' + pt(0.1, 0.04) + 'L' + pt(0.9, 0.04), 'fill="none" stroke="#b4bac2" stroke-width="0.8" stroke-dasharray="2 1.5"');
		var big = dist(o[0], o[1]) >= 60;

		if (big) {
			out += shape('M' + pt(0.12, 0.74) + 'Q' + pt(0.3, 0.66) + ' ' + pt(0.45, 0.76)
				+ 'M' + pt(0.58, 0.34) + 'Q' + pt(0.75, 0.4) + ' ' + pt(0.88, 0.3)
				+ 'M' + pt(0.1, 0.3) + 'Q' + pt(0.2, 0.24) + ' ' + pt(0.32, 0.29), 'fill="none" stroke="#b4bac2" stroke-width="0.8" stroke-opacity="0.5"');
		}

		var sheet = [F(0.28, 0.3), F(0.72, 0.33), F(0.26, 0.7)];

		return '<g>' + out + labelMarkup(faceMap(sheet[0], sheet[1], sheet[2])) + '</g>';
	}

	function cotasMarkup(z, T, labels) {
		var b = z.box;
		var P = z.f.P;

		function seg(a, c, nx, ny, text, anchor) {
			var pts = T([a, c]);
			var x1 = pts[0][0] + nx;
			var y1 = pts[0][1] + ny;
			var x2 = pts[1][0] + nx;
			var y2 = pts[1][1] + ny;
			var color = '#6b7280';

			return '<line x1="' + x1 + '" y1="' + y1 + '" x2="' + x2 + '" y2="' + y2 + '" stroke="' + color + '" stroke-width="1"/>'
				+ '<circle cx="' + x1 + '" cy="' + y1 + '" r="2" fill="' + color + '"/><circle cx="' + x2 + '" cy="' + y2 + '" r="2" fill="' + color + '"/>'
				+ '<text x="' + ((x1 + x2) / 2 + nx * 0.9) + '" y="' + ((y1 + y2) / 2 + ny * 0.9 + 4) + '" text-anchor="' + anchor
				+ '" font-size="11" font-weight="600" fill="#374151">' + escapeHtml(text) + '</text>';
		}

		return seg(P(0, b.d, 0), P(b.w, b.d, 0), -6, 10, labels.w, 'middle')
			+ seg(P(b.w, 0, 0), P(b.w, b.d, 0), 6, 10, labels.d, 'start')
			+ seg(P(0, b.d, 0), P(0, b.d, b.h), -10, 0, labels.h, 'end');
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

		var more = 0;

		boxes = boxes.filter(function (box) {
			more += box.more || 0;

			return !box.more;
		});

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

		var multiLabel = inner.concat(shells).filter(function (z) { return z.box.label && z.box.t !== 'bag'; }).length > 1;

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

			if (box.t === 'bag') {
				out += bagMarkup(z, T);
			}

			if (box.tape) {
				var tape = T([f.P(box.w / 2, 0, box.h), f.P(box.w / 2, box.d, box.h)]);

				out += '<line x1="' + tape[0][0] + '" y1="' + tape[0][1] + '" x2="' + tape[1][0] + '" y2="' + tape[1][1]
					+ '" stroke="rgba(120,80,30,' + (shell ? 0.25 : 0.35) + ')" stroke-width="' + Math.max(2, Math.min(box.w, box.d) * scale * 0.07) + '"/>';
			}

			if (box.label) {
				out += box.env ? boxLabelMarkup(box.env, faces(box.env), T, multiLabel) : boxLabelMarkup(box, f, T, multiLabel);
			}
		}

		inner.forEach(draw);
		shells.forEach(draw);

		if (options.cotas && inner.length) {
			out += cotasMarkup(inner[0], T, options.cotas);
		}

		if (more && W >= 100) {
			out += '<text x="' + Math.min(W - 4, ox + maxX * scale).toFixed(1) + '" y="' + Math.min(H - 4, oy + maxY * scale).toFixed(1) + '" text-anchor="end" font-size="13" font-weight="700" fill="#6b7280">+' + more + '</text>';
		}

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

		var boxes = [{ x: x, y: y, z: 0, w: p.w, d: p.d, h: p.base, t: theme }];

		for (var i = 1; i < p.units; i++) {
			boxes.push({ x: x, y: y, z: p.base + p.inc * (i - 1), w: p.w, d: p.d, h: p.inc, t: theme });
		}

		if (label) {
			var top = boxes[boxes.length - 1];

			top.label = true;
			top.env = { x: x, y: y, z: 0, w: p.w, d: p.d, h: p.base + p.inc * (p.units - 1) };
		}

		return boxes;
	}

	var SEPARATE_MAX = 8;
	var SINGLE_ROW_MIN = 0.75;

	function kindOf(p) {
		return stacked(p)
			? 's' + Math.min(p.w, p.d) + 'x' + Math.max(p.w, p.d) + 'x' + p.h + 'x' + p.units
			: [p.w, p.d, p.h].sort(function (a, b) { return a - b; }).join('x');
	}

	function byVolume(a, b) {
		return (b.w * b.d * b.h) - (a.w * a.d * a.h);
	}

	function pick(items, max) {
		if (items.length <= max) {
			return items;
		}

		var groups = {};
		var order = [];
		var out = [];

		items.forEach(function (p) {
			var key = kindOf(p);

			if (!groups[key]) {
				groups[key] = [];
				order.push(key);
			}

			groups[key].push(p);
		});

		while (out.length < max) {
			order.forEach(function (key) {
				if (out.length < max && groups[key].length) {
					out.push(groups[key].shift());
				}
			});
		}

		return out.sort(byVolume);
	}

	function aligned(items) {
		var seen = {};

		return items.map(function (p) {
			var key = kindOf(p);

			if (!seen[key]) {
				seen[key] = p;
				return p;
			}

			var first = seen[key];

			return Object.assign({}, p, stacked(p) ? { w: first.w, d: first.d } : { w: first.w, d: first.d, h: first.h });
		});
	}

	function spread(items, count, gap) {
		var total = items.reduce(function (acc, p) { return acc + p.w + p.d + gap; }, 0) - gap;
		var target = total / count;
		var rows = [[]];
		var width = 0;

		items.forEach(function (p) {
			var row = rows[rows.length - 1];

			if (row.length && width + p.w + p.d > target && rows.length < count) {
				rows.push([]);
				row = rows[rows.length - 1];
				width = 0;
			}

			row.push(p);
			width += p.w + p.d + gap;
		});

		return rows;
	}

	function place(rows, gap) {
		var rowWidths = rows.map(function (row) {
			return row.reduce(function (acc, p) { return acc + p.w + p.d + gap; }, 0) - gap;
		});
		var widest = Math.max.apply(null, rowWidths);
		var sum = 0;
		var placed = [];

		rows.forEach(function (row, r) {
			var foot = 0;
			var tall = 0;
			var cursor = (widest - rowWidths[r]) / 2;

			row.forEach(function (p) {
				var u = cursor + p.d;

				placed.push({ p: p, x: (sum + u) / 2, y: (sum - u) / 2 });
				cursor = u + p.w + gap;
				foot = Math.max(foot, p.w + p.d);
				tall = Math.max(tall, p.h);
			});

			var next = rows[r + 1];

			if (next) {
				var nextTall = next.reduce(function (acc, p) { return Math.max(acc, p.h); }, 0);

				sum += foot + 2 * nextTall + 2 * gap;
			}
		});

		return placed;
	}

	function span(placed) {
		var minX = 1e9;
		var maxX = -1e9;
		var minY = 1e9;
		var maxY = -1e9;

		placed.forEach(function (it) {
			minX = Math.min(minX, it.x - it.y - it.p.d);
			maxX = Math.max(maxX, it.x + it.p.w - it.y);
			minY = Math.min(minY, (it.x + it.y) / 2 - it.p.h);
			maxY = Math.max(maxY, (it.x + it.y + it.p.w + it.p.d) / 2);
		});

		return { w: (maxX - minX) * COS_30, h: maxY - minY };
	}

	function separate(pk, theme) {
		var all = aligned(pk.slice().sort(byVolume));
		var items = pick(all, SEPARATE_MAX);

		if (!items.length) {
			return [];
		}

		var biggest = items.reduce(function (acc, p) { return Math.max(acc, p.w, p.d, p.h); }, 0);
		var gap = Math.max(1, 0.12 * biggest);
		var groups = [];
		var byKind = {};

		items.forEach(function (p) {
			var key = kindOf(p);

			if (!byKind[key]) {
				byKind[key] = [];
				groups.push(byKind[key]);
			}

			byKind[key].push(p);
		});

		function candidate(rows) {
			var placed = place(rows, gap);
			var s = span(placed);

			return { placed: placed, score: Math.min(1.8 / s.w, 1 / s.h) };
		}

		var best = null;

		for (var count = 1; count <= Math.min(3, items.length); count++) {
			var rows = [];

			groups.forEach(function (g) {
				rows = rows.concat(spread(g, Math.min(count, g.length), gap));
			});

			var c = candidate(rows);

			if (!best || c.score > best.score) {
				best = c;
			}
		}

		if (groups.length > 1) {
			var single = candidate([items]);

			if (single.score >= best.score * SINGLE_ROW_MIN) {
				best = single;
			}
		}

		var out = [];

		best.placed.forEach(function (it) {
			out = out.concat(boxesOf(it.p, it.x, it.y, theme, true));
		});

		if (all.length > items.length) {
			out.push({ more: all.length - items.length });
		}

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
		if (pk.some(function (p) { return p.fixed; })) {
			return { boxes: separate(sample(pk), 'kraft'), info: null };
		}

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

	var MAX_BOX_PIECES = 500;
	var BOX_FIT_MARGIN = 1;
	var BAG_VOLUME_TOLERANCE = 1.1;

	function boxPieces(pk) {
		if (!pk.length || pk.some(function (p) { return p.fixed; }) || totalCount(pk) > MAX_BOX_PIECES) {
			return null;
		}

		var out = [];

		pk.forEach(function (p) {
			for (var i = 0; i < countOf(p); i++) {
				out.push(p);
			}
		});

		return out;
	}

	function boxVolume(box) {
		return box.length * box.width * box.height;
	}

	function boxFits(box, need, volume) {
		if (box.type === 'bolsa') {
			return volume <= boxVolume(box) * BAG_VOLUME_TOLERANCE
				&& need[0] <= Math.max(box.length, box.width)
				&& need[1] <= Math.min(box.length, box.width)
				&& need[2] <= box.height;
		}

		var own = [box.length, box.width, box.height].sort(function (a, b) { return b - a; });

		return need[0] <= own[0] && need[1] <= own[1] && need[2] <= own[2];
	}

	function pickBox(pk, boxes) {
		boxes = boxes || cfg.storeBoxes;

		var pieces = boxPieces(pk);
		var pack = boxes.length && pieces ? bestPack(pieces) : null;

		if (!pack) {
			return null;
		}

		var sides = [pack.W, pack.D, pack.H].sort(function (a, b) { return b - a; });
		var need = sides.map(function (side) { return side + BOX_FIT_MARGIN; });
		var volume = pieces.reduce(function (acc, p) { return acc + p.w * p.d * p.h; }, 0);

		return boxes.filter(function (box) {
			return box.type !== 'juego' && boxFits(box, need, volume);
		}).sort(function (a, b) {
			return boxVolume(a) - boxVolume(b);
		})[0] || null;
	}

	function boxPiece(box) {
		var l = toCm(box.length);
		var w = toCm(box.width);
		var h = toCm(box.height);

		if (box.type === 'bolsa') {
			var across = Math.min(l, w);

			return [{ x: 0, y: 0, z: 0, w: across, d: Math.max(h, across * 0.2), h: Math.max(l, w), t: 'bag' }];
		}

		return [{ x: 0, y: 0, z: 0, w: l, d: w, h: h, tape: true, label: true, t: 'kraft' }];
	}

	function boxDims(box) {
		var sides = box.type === 'bolsa' ? [box.length, box.width] : [box.length, box.width, box.height];

		return sides.map(function (side) { return fmt(side, 2); }).join('×');
	}

	function boxAdvice(pk, options) {
		options = options || {};

		var s = cfg.i18n;
		var box = options.storeBox || null;

		if (!box && options.forceBigger) {
			return { box: null, html: '' };
		}

		box = box || pickBox(pk);

		if (box) {
			return { box: box, html: '<span class="andr-dispatch__note andr-dispatch__note--box">' + escapeHtml(fill(s.box_use, box.name, boxDims(box))) + '</span>' };
		}

		var info = packed(pk).info;

		if (!info) {
			return { box: null, html: '' };
		}

		var hint = cfg.storeBoxes.length
			? escapeHtml(s.box_no_fit)
			: (cfg.boxesUrl ? '<a href="' + escapeHtml(cfg.boxesUrl) + '">' + escapeHtml(s.box_load) + '</a>' : escapeHtml(s.box_load));

		return {
			box: null,
			suggested: info,
			hint: hint,
			html: '<span class="andr-dispatch__note andr-dispatch__note--box">' + escapeHtml(fill(s.box_suggested, info.w, info.d, info.h)) + ' · ' + hint + '</span>'
		};
	}

	function totalKg(pk) {
		return pk.reduce(function (acc, p) { return acc + p.kg * countOf(p); }, 0);
	}

	function why(pk, perBox) {
		var l = cfg.limits;
		var kg = perBox ? Math.max.apply(null, pk.map(function (p) { return p.kg; })) : totalKg(pk);

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
		var key = { weight: 'why_weight', max_side: 'why_max_side', sum_sides: 'why_sum_sides', size_side: 'size_side', size_sum: 'size_sum' }[reason.type];

		return key ? fill(cfg.i18n[key], fmt(reason.value, 1)) : '';
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

	function incompleteText(list) {
		var numbers = [];

		(list || []).forEach(function (b, i) {
			if (!bultoComplete(b)) {
				numbers.push(i + 2);
			}
		});

		return numbers.length ? fill(cfg.i18n.piece_complete, numbers[0]) : '';
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

		if (p.mode === 'single' && p.unitsPerBox > 1) {
			kg *= p.unitsPerBox;
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

		return { missing: missing, reason: missing ? null : (why(productPackages(p)) || (p.mode === 'multibulto' && cfg.overflowSupported ? { type: 'multibox', count: 1 + completeBultos(p.bultos).length } : null)) };
	}

	var PREVIEW_MAX = 14;
	var bigger = false;

	var KIND_MODE = { box: 'single', stack: 'apilado', multi: 'multibulto' };

	function exceptionView(raw) {
		var on = raw === 'separate';

		return {
			on: on,
			stored: on ? 'separate' : 'inherit'
		};
	}

	function syncCapacity($root, state) {
		var $box = $root.find('[data-andr="capacity"]').first();
		var $ask = $root.find('[data-andr="overflow"]').first();
		var stack = state.mode === 'apilado';
		var multi = state.mode === 'multibulto';
		var supported = cfg.overflowSupported && $ask.attr('data-overflow-supported') !== '0';
		var $input = $root.find('[data-andr="overflow-input"]');
		var view = exceptionView($input.val());
		var effective = supported && view.on ? 'separate' : 'together';
		var wantsBigger = (multi && supported) || effective === 'separate';
		var $units = $box.find('[data-andr="capacity-units"] .andr-stepper-xl__input');
		var $max = $root.find('[data-stack="max"]');
		var last = $box.attr('data-last-mode');

		$input.val(view.stored);

		if (last !== state.mode) {
			if (stack && (parseInt($max.val(), 10) || 0) < 2) {
				$max.val(Math.max(2, parseInt($units.val(), 10) || 2));
			}

			$box.attr('data-last-mode', state.mode);
		}

		$box.find('[data-andr="capacity-units"]').prop('hidden', state.mode !== 'single');
		$root.find('[data-andr="stack-fields"]').prop('hidden', !stack);
		$root.find('[data-andr="kind"] input[type="radio"]').each(function () {
			this.checked = KIND_MODE[this.value] === state.mode;
		});
		$root.find('[data-andr="capacity-overflow-wrap"]').prop('hidden', !supported || multi);
		$root.find('[data-andr="overflow"] .andr-opt__input').each(function () {
			this.checked = (this.value === 'separate') === view.on;
		});
		$root.find('[data-andr="no-bigger"]').prop('hidden', !((wantsBigger || bigger) && $ask.attr('data-no-bigger') === '1'));
	}

	function bindCapacity() {
		var $ = window.jQuery;
		var CAP = '[data-andr="capacity"]';

		$(document).on('click', CAP + ' [data-units-step], ' + CAP + ' [data-stack-step]', function () {
			var step = $(this).attr('data-units-step') || $(this).attr('data-stack-step');
			var $input = $(this).closest('.andr-stepper-xl').find('.andr-stepper-xl__input');
			var min = parseInt($input.attr('min'), 10) || 1;
			var next = Math.max(min, (parseInt($input.val(), 10) || min) + parseInt(step, 10));

			$input.val(next).trigger('input');
		});

		$(document).on('change', '[data-andr="overflow"] .andr-opt__input', function () {
			$(this).closest('[data-andr="overflow"]').find('[data-andr="overflow-input"]')
				.val(this.value === 'separate' ? 'separate' : 'inherit').trigger('change');
		});

		$(document).on('change', '[data-andr="kind"] input[type="radio"]', function () {
			$(this).closest('[data-andr="kind"]').find('[data-andr="mode-input"]')
				.val(KIND_MODE[this.value] || 'single').trigger('change');
		});
	}

	function serviceIcon(service) {
		return cfg.serviceIcons[service]
			? '<img class="andr-service-icon" src="' + escapeHtml(cfg.serviceIcons[service]) + '" alt="" aria-hidden="true">'
			: '';
	}

	function applyBadge($badge, evaluation, labels) {
		var state = evaluation.missing ? 'missing' : (evaluation.reason ? 'bigger' : 'ok');
		var modifier = { missing: 'warning', bigger: 'info', ok: 'success' }[state];
		bigger = state === 'bigger';
		var texts = labels || { missing: cfg.i18n.status_missing, bigger: cfg.i18n.status_bigger, ok: cfg.i18n.status_ok };
		var label = texts[state];
		var $text = $badge.find('[data-andr="badge-text"]');

		$badge
			.removeClass('andr-badge--warning andr-badge--info andr-badge--success')
			.addClass('andr-badge--' + modifier);

		($text.length ? $text : $badge).text(label || '');
		$badge.find('.andr-service-icon').remove();
		$badge.prepend(serviceIcon({ bigger: 'bigger', ok: 'paqueteria' }[state]));
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
			ref: p.ref ? String(p.ref) : '',
			fixed: !!p.fixed,
			line: p.line === undefined || p.line === null ? null : String(p.line),
			lineQty: parseInt(p.line_qty, 10) || 0,
			assigned: p.assigned && p.assigned.name ? p.assigned : null
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

	function measureText(p) {
		return (p.ref ? p.ref + ': ' : '') + dims(p) + ' cm · ' + weightText(p.kg);
	}

	function assignedText(a, labels) {
		return a.type === 'juego' && a.parts && a.parts.length
			? fill(labels.useSet, a.name, a.parts.join(' + '))
			: fill(labels.inBox, a.name);
	}

	function cards(pk, labels) {
		var offset = 0;
		var byLine = {};
		var out = [];

		pk.forEach(function (p) {
			var count = countOf(p);
			var key = p.assigned && p.line !== null ? p.line : null;

			if (key !== null && byLine[key]) {
				byLine[key].packages.push(p);
				byLine[key].title += '\n' + measureText(p);
				offset += count;

				return;
			}

			if (key !== null) {
				var a = p.assigned;
				var card = {
					head: fmt(p.lineQty || count) + ' × ' + (p.name || labels.box),
					detail: assignedText(a, labels),
					title: measureText(p),
					assigned: true,
					packages: [p]
				};

				byLine[key] = card;
				out.push(card);
				offset += count;

				return;
			}

			var head = count > 1
				? [fmt(count) + ' × ' + (p.name || labels.box), p.ref]
				: [labels.box + ' ' + (offset + 1), p.name, p.ref];

			out.push({
				head: head.filter(Boolean).join(' · '),
				detail: [
					p.units >= (labels.minUnits || 1) ? fill(p.units === 1 && labels.unit ? labels.unit : labels.units, p.units) : '',
					dims(p) + ' cm',
					weightText(p.kg)
				].filter(Boolean).join(' · '),
				title: '',
				assigned: false,
				packages: [p]
			});

			offset += count;
		});

		return out;
	}

	function boxList(ul, pk, labels) {
		var list = cards(pk, labels);

		ul.innerHTML = list.map(function (card) {
			var text = card.assigned
				? '<span>' + escapeHtml(card.head) + '<small class="andr-boxlist__sub">' + escapeHtml(card.detail) + '</small></span>'
				: '<span>' + escapeHtml([card.head, card.detail].filter(Boolean).join(' · ')) + '</span>';

			return '<li class="andr-boxlist__item"' + (card.title ? ' title="' + escapeHtml(card.title) + '"' : '') + '><svg class="andr-boxlist__thumb" role="img" aria-hidden="true"></svg>'
				+ text + '</li>';
		}).join('');

		Array.prototype.forEach.call(ul.querySelectorAll('svg'), function (svg, i) {
			var group = list[i].packages;

			render(svg, group.length > 1 ? separate(group, 'kraft') : boxesOf(group[0], 0, 0, 'kraft', false), { W: 48, H: 40, pad: 3 });
		});
	}

	function cardLabels() {
		return {
			box: cfg.i18n.box_word,
			units: cfg.i18n.box_units,
			unit: cfg.i18n.box_unit,
			useSet: cfg.i18n.pkg_use_set,
			inBox: cfg.i18n.pkg_in_box
		};
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
		boxList(host.querySelector('ul'), pk, cardLabels());
		host.hidden = false;
		stage.style.display = 'none';
	}

	var STAGE = { W: 420, H: 230, pad: 16, fit: true };

	function resultClasses(modifier) {
		return 'andr-dispatch__result andr-dispatch__result--' + modifier;
	}

	function sizeReason(info) {
		var longest = Math.max(info.w, info.d, info.h);

		return longest > cfg.limits.max_side
			? { type: 'size_side', value: longest, limit: cfg.limits.max_side }
			: { type: 'size_sum', value: info.w + info.d + info.h, limit: cfg.limits.sum_sides };
	}

	function packResult(pack, stageSvg, resultEl) {
		var html = '<span class="andr-dispatch__result-title">' + serviceIcon('paqueteria') + '<span>' + escapeHtml(cfg.i18n.labels_one_pack) + '</span></span>'
			+ '<span class="andr-dispatch__note">' + escapeHtml(cfg.i18n.pack_home_or_branch) + '</span>';

		render(stageSvg, pack.boxes, STAGE);

		resultEl.className = resultClasses('ok');
		resultEl.innerHTML = html;
	}

	function biggerResult(pk, reason, mode, stageSvg, resultEl, cart, mixed, list, forceNote, pack) {
		var s = cfg.i18n;
		var joined = !!(pack && pack.info);
		var count = totalCount(pk);
		var title = joined || count === 1 ? s.labels_one_big : fill(s.labels_many_big, fmt(count));
		var note = [];

		if (forceNote) {
			note.push(forceNote);
		} else {
			var because = reasonText(reason);

			if (because) {
				note.push(because);
			}

			if (joined && count > 1) {
				note.push(s.pack_joined);
			} else if (cart && mixed) {
				note.push(s.cart_big_mixed);
			} else if (!cart && mode === 'apilado' && pk.some(stacked)) {
				note.push(s.big_per_pile);
			}
		}

		if (joined) {
			showBoxList(stageSvg, null);
			render(stageSvg, pack.boxes, STAGE);
		} else if (list && (heterogeneous(pk) || pk.some(function (p) { return p.assigned; }))) {
			showBoxList(stageSvg, pk);
		} else {
			showBoxList(stageSvg, null);
			render(stageSvg, separate(sample(pk), 'kraft'), STAGE);
		}

		resultEl.className = resultClasses('big');
		resultEl.innerHTML = '<span class="andr-dispatch__result-title">' + serviceIcon('bigger') + '<span>' + escapeHtml(title) + '</span></span>'
			+ '<span class="andr-dispatch__note">' + escapeHtml(s.big_home_only) + '</span>'
			+ (note.length ? '<span class="andr-dispatch__note">' + escapeHtml(note.join(' · ')) + '</span>' : '');
	}

	function forcedReason(pk, options) {
		return options.forceReason === 'multibulto'
			? { type: 'multibox', count: totalCount(pk) }
			: { type: 'overflow' };
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
			return false;
		}

		var reason = options.forceBigger ? (why(pk, true) || forcedReason(pk, options)) : why(pk);
		var pack = null;

		if (!reason) {
			pack = packed(pk);
			reason = pack.info && !pack.info.ok ? sizeReason(pack.info) : null;
		}

		if (reason) {
			biggerResult(pk, reason, options.mode, options.svg, options.result, options.cart, options.mixed, options.list, options.forceNote, pack);
		} else {
			packResult(pack, options.svg, options.result);
		}

		var advice = options.advice && (!reason || (pack && pack.info)) ? boxAdvice(pk, options).html : '';

		if (advice) {
			options.result.insertAdjacentHTML('beforeend', advice);
		}

		return !!reason;
	}

	function createPreview(options) {
		var $ = window.jQuery;
		var $root = $(options.root);
		var $qtyInput = $root.find('[data-andr="qty-input"]');
		var $qtyUnit = $root.find('[data-andr="qty-unit"]');
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

			var bigger = paint({ svg: svg, result: result, packages: packages, quantity: q, mode: entry.mode, forceBigger: !!entry.force[q], forceReason: entry.mode === 'multibulto' ? 'multibulto' : 'separate', advice: true });

			if (packages.length && options.onResult) {
				options.onResult(bigger);
			}

			var hint = packages.length && options.hint ? options.hint() : '';

			if (hint) {
				result.insertAdjacentHTML('beforeend', '<span class="andr-dispatch__note">' + escapeHtml(hint) + '</span>');
			}

			$detail.html(entry.html);
		}

		function refresh() {
			clearTimeout(timer);

			var blocked = options.blocked ? options.blocked() : '';

			if (blocked) {
				request++;
				AndreaniLoader.hide($result);
				paint({ svg: svg, result: result, packages: [], emptyText: blocked });
				$detail.empty();
				return;
			}

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
					max_quantity: cache[key] ? 0 : PREVIEW_MAX
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

							cache[key] = { byQuantity: {}, force: {}, html: res.data.html, mode: draft.dispatch_mode };
							cached++;
						}

						$.extend(cache[key].byQuantity, res.data.packages_by_quantity || {});
							$.extend(cache[key].force, res.data.force_bigger || {});
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

		$root.on('click', '[data-qty-step]', function () {
			var q = Math.min(99, Math.max(1, (parseInt($qtyInput.val(), 10) || current) + parseInt($(this).attr('data-qty-step'), 10)));

			$qtyInput.val(q).trigger('input');
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
		},
		together: function () {
			return [{ x: 0, y: 0, z: 0, w: 30, d: 30, h: 30, tape: true, label: true }];
		},
		separate: function () {
			return [{ x: 0, y: 0, z: 0, w: 30, d: 30, h: 30, tape: true, label: true }, { x: 36, y: 0, z: 0, w: 30, d: 30, h: 30, tape: true, label: true }];
		},
		box: function () {
			return [{ x: 0, y: 0, z: 0, w: 30, d: 30, h: 30, tape: true }];
		},
		multi: function () {
			return [{ x: 0, y: 0, z: 0, w: 22, d: 22, h: 22, tape: true }, { x: 28, y: 0, z: 0, w: 22, d: 22, h: 22, tape: true }, { x: 56, y: 0, z: 0, w: 22, d: 22, h: 22, tape: true }];
		}
	};

	var LABEL_ART = { label_one: 1, label_many: 2 };

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
		Array.prototype.forEach.call((root || document).querySelectorAll('[data-andr-art]:not([data-andr-ready])'), function (svg) {
			var key = svg.getAttribute('data-andr-art');
			var W = parseFloat(svg.getAttribute('width')) || 64;
			var H = parseFloat(svg.getAttribute('height')) || 44;

			if (LABEL_ART[key]) {
				renderLabels(svg, LABEL_ART[key], W, H);
			} else if (ART[key]) {
				render(svg, ART[key](), { W: W, H: H, pad: 2 });
			} else {
				return;
			}

			svg.setAttribute('data-andr-ready', '');
		});
	}

	function watchArt() {
		if (!window.MutationObserver) {
			return;
		}

		new MutationObserver(function (changes) {
			changes.forEach(function (change) {
				Array.prototype.forEach.call(change.addedNodes, function (node) {
					if (node.nodeType === 1 && node.querySelector && node.querySelector('[data-andr-art]:not([data-andr-ready])')) {
						mountArt(node);
					}
				});
			});
		}).observe(document.documentElement, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			mountArt();
			watchArt();
		});
	} else {
		mountArt();
		watchArt();
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
				? separate(sample(packages.map(drawPackage)), 'kraft').filter(function (b) {
					return !b.more;
				}).map(function (b) {
					return Object.assign({}, b, { label: false });
				})
				: [{ x: 0, y: 0, z: 0, w: 30, d: 30, h: 30, t: 'gray' }];

			render(svg, boxes, { W: 48, H: 48, pad: 3 });
		});
	}

	if (window.jQuery) {
		bindCapacity();
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
		cards: function (pk) {
			return cards(pk.map(drawPackage), Object.assign(cardLabels(), { minUnits: 2 }));
		},
		assignedText: function (a) {
			return assignedText(a, cardLabels());
		},
		useBoxText: function (name) {
			return fill(cfg.i18n.pkg_use_box, name);
		},
		weightText: weightText,
		packed: packed,
		why: why,
		reasonText: reasonText,
		summary: summary,
		fmt: fmt,
		fill: fill,
		sizeReasonText: function (info) {
			return reasonText(sizeReason(info));
		},
		i18n: function () {
			return cfg.i18n;
		},
		pickBox: pickBox,
		boxDims: boxDims,
		boxPiece: boxPiece,
		serviceIcon: serviceIcon,
		boxAdvice: boxAdvice,
		evaluateProduct: evaluateProduct,
		completeBultos: completeBultos,
		ignoredHint: ignoredHint,
		incompleteText: incompleteText,
		applyBadge: applyBadge,
		syncCapacity: syncCapacity,
		previewMax: PREVIEW_MAX,
		bindCapacity: bindCapacity,
		paint: paint,
		createPreview: createPreview,
		mountIcons: mountIcons,
		mountArt: mountArt,
		dimField: dimField,
		mountThumbs: mountThumbs,
		countOf: countOf,
		packages: function () {
			return cfg.packages || [];
		},
		setPackages: function (list) {
			cfg.packages = list || [];
		},
		packageApi: function () {
			return cfg.packageApi || {};
		},
		cmFactor: function () {
			return cfg.cmFactor;
		}
	};
})(window);
