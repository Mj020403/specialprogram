(function () {
  var metricDefs = {
    support_total: { label: 'Needs support', color: '#f97316', legend: 'Families in needs support, validation, or high-risk queue.' },
    qualified_total: { label: 'Qualified', color: '#22c55e', legend: 'Qualified and highly qualified families.' },
    overdue_monitoring_total: { label: 'Monitoring overdue', color: '#ef4444', legend: 'Families without a recent monitoring visit.' },
    assistance_total: { label: 'Assistance', color: '#0ea5e9', legend: 'Assistance records released to households.' },
    attendance_total: { label: 'Attendance', color: '#8b5cf6', legend: 'Present or late event attendance records.' },
    avg_score: { label: 'Average score', color: '#eab308', legend: 'Average qualification score by barangay.' }
  };

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function valueForMetric(row, metric) {
    var value = Number(row && row[metric] !== undefined ? row[metric] : 0);
    return Number.isFinite(value) ? value : 0;
  }

  function numberDisplay(value, metric) {
    return metric === 'avg_score' ? Number(value || 0).toFixed(1) : String(Math.round(Number(value || 0)));
  }

  function colorForMetric(value, maxValue, metric) {
    if (metric === 'avg_score') {
      if (value >= 70) return '#16a34a';
      if (value >= 45) return '#eab308';
      if (value > 0) return '#f97316';
      return '#94a3b8';
    }
    if (metric === 'qualified_total') {
      var qr = maxValue > 0 ? value / maxValue : 0;
      if (qr >= 0.66) return '#15803d';
      if (qr >= 0.33) return '#4ade80';
      return '#bbf7d0';
    }
    var ratio = maxValue > 0 ? value / maxValue : 0;
    if (ratio >= 0.66) return '#dc2626';
    if (ratio >= 0.33) return '#f97316';
    if (ratio > 0.1) return '#facc15';
    return metricDefs[metric] ? metricDefs[metric].color : '#64748b';
  }

  function hotspotRadius(value, maxValue) {
    if (maxValue <= 0) return 8;
    var scaled = Math.sqrt(value / maxValue);
    return Math.max(8, Math.min(22, 8 + scaled * 14));
  }

  function statusColor(status) {
    var key = String(status || '').toLowerCase();
    if (key === 'high risk') return '#dc2626';
    if (key === 'needs support') return '#f97316';
    if (key === 'for validation') return '#0ea5e9';
    if (key === 'qualified' || key === 'highly qualified') return '#16a34a';
    return '#64748b';
  }

  function badgeClass(status) {
    var key = String(status || '').toLowerCase();
    if (key === 'high risk') return 'app-badge-red';
    if (key === 'needs support') return 'app-badge-amber';
    if (key === 'for validation') return 'app-badge-sky';
    if (key === 'qualified' || key === 'highly qualified') return 'app-badge-emerald';
    return 'app-badge-slate';
  }

  function statCard(label, value, hint) {
    return '<div class="app-geo-stat"><div class="app-geo-stat-label">' + escapeHtml(label) + '</div><div class="app-geo-stat-value">' + escapeHtml(value) + '</div><div class="app-geo-stat-hint">' + escapeHtml(hint) + '</div></div>';
  }

  function watchlistMarkup(rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
      return '<div class="text-sm text-slate-500">No highlighted household yet.</div>';
    }
    return rows.map(function (row) {
      var actions = Array.isArray(row.pending_actions) && row.pending_actions.length
        ? row.pending_actions.map(function (action) { return '<span class="app-geo-inline-pill">' + escapeHtml(action) + '</span>'; }).join(' ')
        : '<span class="app-geo-inline-pill is-ok">Operationally ready</span>';
      var openUrl = '/harvest/modules/agri/households/view.php?id=' + encodeURIComponent(row.household_id);
      return '<div class="app-geo-watch-card">'
        + '<div class="flex items-start justify-between gap-3">'
        + '<div><div class="font-semibold">' + escapeHtml(row.household_head_name) + '</div><div class="text-xs text-slate-500 mt-1">' + escapeHtml(row.household_code || 'No code') + ' · Score ' + Number(row.score || 0).toFixed(0) + '</div></div>'
        + '<span class="app-badge ' + badgeClass(row.qualification_status) + '">' + escapeHtml(row.qualification_status) + '</span>'
        + '</div>'
        + '<div class="mt-3 text-xs text-slate-500">' + (row.last_monitoring_date ? 'Last monitoring: ' + escapeHtml(row.last_monitoring_date) : 'No monitoring record yet') + '</div>'
        + '<div class="mt-3">' + actions + '</div>'
        + '<div class="mt-3"><a class="app-geo-open-link" href="' + escapeHtml(openUrl) + '">Open family record</a></div>'
        + '</div>';
    }).join('');
  }

  function routeMarkup(batches) {
    if (!Array.isArray(batches) || batches.length === 0) {
      return '<div class="text-sm text-slate-500">No route suggestion yet. Add GPS coordinates to family records so the system can build field batches.</div>';
    }
    return batches.map(function (batch) {
      var stops = Array.isArray(batch.households) ? batch.households.map(function (stop) {
        return '<div class="app-geo-route-stop">'
          + '<div class="app-geo-route-stop-num">' + escapeHtml(stop.stop_order) + '</div>'
          + '<div class="min-w-0"><div class="font-semibold truncate">' + escapeHtml(stop.household_head_name) + '</div>'
          + '<div class="text-xs text-slate-500">' + escapeHtml(stop.household_code || 'No code') + ' · ' + escapeHtml(stop.qualification_status || 'For Validation') + ' · +' + escapeHtml(Number(stop.distance_from_previous_km || 0).toFixed(2)) + ' km</div></div>'
          + '</div>';
      }).join('') : '';
      return '<div class="app-geo-route-card">'
        + '<div class="flex items-center justify-between gap-3"><div><div class="font-black">' + escapeHtml(batch.batch_label || 'Route') + '</div><div class="text-xs text-slate-500">' + escapeHtml(batch.household_count || 0) + ' family stop(s)</div></div>'
        + '<div class="text-right"><div class="text-xs text-slate-500">Estimated distance</div><div class="text-lg font-black">' + escapeHtml(Number(batch.estimated_distance_km || 0).toFixed(2)) + ' km</div></div></div>'
        + '<div class="mt-3 flex flex-wrap gap-2">'
        + '<span class="app-geo-inline-pill">Overdue: ' + escapeHtml(batch.overdue_count || 0) + '</span>'
        + '<span class="app-geo-inline-pill">Risk: ' + escapeHtml(batch.high_risk_count || 0) + '</span>'
        + '</div>'
        + '<div class="mt-3 space-y-2">' + stops + '</div>'
        + '</div>';
    }).join('');
  }

  function rankingMarkup(rows, metric) {
    if (!Array.isArray(rows) || rows.length === 0) {
      return '<div class="text-sm text-slate-500">No barangay metrics available.</div>';
    }
    var maxValue = Math.max.apply(null, rows.map(function (row) { return valueForMetric(row, metric); }).concat([0]));
    return rows.slice(0, 5).map(function (row, index) {
      var value = valueForMetric(row, metric);
      var width = maxValue > 0 ? Math.max(12, Math.round((value / maxValue) * 100)) : 12;
      return '<button type="button" class="app-geo-rank-item" data-geo-focus="' + escapeHtml(row.barangay_id) + '">'
        + '<div class="flex items-center gap-3"><span class="app-geo-rank-num">' + (index + 1) + '</span><div><div class="font-semibold text-left">' + escapeHtml(row.barangay_name) + '</div><div class="text-xs text-slate-500 text-left">' + escapeHtml(row.households_total || 0) + ' households · ' + escapeHtml(Number(row.avg_score || 0).toFixed(1)) + ' avg score</div></div></div>'
        + '<div class="text-right min-w-[5rem]"><div class="font-black">' + escapeHtml(numberDisplay(value, metric)) + '</div><div class="app-geo-rank-bar"><span style="width:' + width + '%"></span></div></div>'
        + '</button>';
    }).join('');
  }

  function updateSelected(widget, row, metric) {
    if (!row) return;
    var metricDef = metricDefs[metric] || metricDefs.support_total;
    var titleEl = widget.querySelector('[data-geo-selected-title]');
    var subtitleEl = widget.querySelector('[data-geo-selected-subtitle]');
    var statsEl = widget.querySelector('[data-geo-selected-stats]');
    var watchEl = widget.querySelector('[data-geo-selected-watchlist]');
    var routesEl = widget.querySelector('[data-geo-selected-routes]');
    if (titleEl) titleEl.textContent = row.barangay_name || 'Matag-ob overview';
    if (subtitleEl) {
      subtitleEl.textContent = metricDef.label + ': ' + numberDisplay(valueForMetric(row, metric), metric)
        + ' · attention index ' + Number(row.attention_index || 0)
        + ' · GPS coverage ' + Number(row.gps_coverage_rate || 0).toFixed(1) + '%';
    }
    if (statsEl) {
      statsEl.innerHTML = ''
        + statCard('Families', row.households_total || 0, 'Profiled households')
        + statCard('Qualified', row.qualified_total || 0, 'Qualified + highly qualified')
        + statCard('Needs support', row.support_total || 0, 'Support + validation + risk')
        + statCard('Monitoring overdue', row.overdue_monitoring_total || 0, 'No recent visit')
        + statCard('Attendance', row.attendance_total || 0, 'Present or late')
        + statCard('Assistance', row.assistance_total || 0, 'Released assistance')
        + statCard('Active crops', row.active_crops_total || 0, 'Crop registry count')
        + statCard('GPS coverage', Number(row.gps_coverage_rate || 0).toFixed(1) + '%', (row.mapped_households_total || 0) + ' mapped family marker(s)');
    }
    if (watchEl) watchEl.innerHTML = watchlistMarkup(row.priority_households || []);
    if (routesEl) routesEl.innerHTML = routeMarkup(row.route_batches || []);
  }

  function ensureLeafletAssets(onReady) {
    if (window.L) {
      onReady();
      return;
    }
    var cssItems = [
      {
        id: 'leaflet-css-harvest',
        href: 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        integrity: 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY='
      },
      {
        id: 'leaflet-markercluster-css-harvest',
        href: 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css'
      },
      {
        id: 'leaflet-markercluster-default-css-harvest',
        href: 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css'
      }
    ];
    cssItems.forEach(function (item) {
      if (document.getElementById(item.id)) return;
      var link = document.createElement('link');
      link.id = item.id;
      link.rel = 'stylesheet';
      link.href = item.href;
      if (item.integrity) {
        link.integrity = item.integrity;
        link.crossOrigin = '';
      }
      document.head.appendChild(link);
    });

    var scripts = [
      {
        id: 'leaflet-js-harvest',
        src: 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        integrity: 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo='
      },
      {
        id: 'leaflet-heat-js-harvest',
        src: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js'
      },
      {
        id: 'leaflet-markercluster-js-harvest',
        src: 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js'
      }
    ];
    var pending = scripts.length;
    function done() {
      pending -= 1;
      if (pending <= 0) onReady();
    }
    scripts.forEach(function (item) {
      if (document.getElementById(item.id)) {
        done();
        return;
      }
      var script = document.createElement('script');
      script.id = item.id;
      script.src = item.src;
      if (item.integrity) {
        script.integrity = item.integrity;
        script.crossOrigin = '';
      }
      script.onload = done;
      script.onerror = done;
      document.body.appendChild(script);
    });
  }

  function initWidget(widget, config) {
    var datasetEl = document.getElementById(config.id + '-data');
    if (!datasetEl) return;
    var payload;
    try {
      payload = JSON.parse(datasetEl.textContent || '{}');
    } catch (err) {
      return;
    }
    if (!payload || !Array.isArray(payload.rows)) return;

    var mapEl = document.getElementById(config.id + '-map');
    var noteText = widget.querySelector('[data-geo-note-text]');
    var layerNote = widget.querySelector('[data-geo-layer-note]');
    var dataNote = widget.querySelector('[data-geo-data-note]');
    var rankingEl = widget.querySelector('[data-geo-ranking-list]');
    var rankedByEl = widget.querySelector('[data-geo-ranked-by]');
    var activeMetric = config.defaultMetric || widget.getAttribute('data-default-metric') || 'support_total';
    var selectedRow = payload.top_priority && payload.top_priority[0] ? payload.top_priority[0] : payload.rows[0];
    var toggles = {
      zones: true,
      households: Array.isArray(payload.household_points) && payload.household_points.length > 0 && payload.household_points.length <= 80,
      routes: false,
      heat: false
    };

    var rowsById = {};
    payload.rows.forEach(function (row) { rowsById[String(row.barangay_id)] = row; });

    widget.querySelectorAll('[data-geo-metric]').forEach(function (button) {
      button.addEventListener('click', function () {
        activeMetric = button.getAttribute('data-geo-metric');
        widget.querySelectorAll('[data-geo-metric]').forEach(function (btn) { btn.classList.remove('is-active'); });
        button.classList.add('is-active');
        draw();
      });
    });

    function bindToggle(selector, key) {
      var button = widget.querySelector(selector);
      if (!button) return;
      if (toggles[key]) button.classList.add('is-active');
      button.addEventListener('click', function () {
        toggles[key] = !toggles[key];
        button.classList.toggle('is-active', toggles[key]);
        draw();
      });
    }
    bindToggle('[data-geo-zone-toggle]', 'zones');
    bindToggle('[data-geo-household-toggle]', 'households');
    bindToggle('[data-geo-route-toggle]', 'routes');
    bindToggle('[data-geo-heat-toggle]', 'heat');

    ensureLeafletAssets(function () {
      if (!window.L || !mapEl) {
        if (mapEl) {
          mapEl.innerHTML = '<div class="app-geo-map-fallback">Map library did not load. Check internet access for Leaflet CDN assets or switch to saved local assets later.</div>';
        }
        return;
      }

      var map = L.map(mapEl, { zoomControl: true, attributionControl: true, scrollWheelZoom: true });
      map.setView([payload.center.lat, payload.center.lng], payload.center.zoom || 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);

      var hotspotLayer = L.layerGroup().addTo(map);
      var routeLayer = L.layerGroup().addTo(map);
      var householdLayer = window.L.markerClusterGroup ? L.markerClusterGroup({ disableClusteringAtZoom: 16, spiderfyOnMaxZoom: true }) : L.layerGroup();
      var zoneLayer = null;
      var heatLayer = null;

      var boundsParts = [];
      if (payload.zones_geojson && Array.isArray(payload.zones_geojson.features) && payload.zones_geojson.features.length) {
        try {
          boundsParts.push(L.geoJSON(payload.zones_geojson).getBounds());
        } catch (e) {}
      }
      var allCoords = payload.rows.filter(function (row) {
        return Number.isFinite(Number(row.lat)) && Number.isFinite(Number(row.lng));
      }).map(function (row) { return [Number(row.lat), Number(row.lng)]; });
      if (allCoords.length) boundsParts.push(L.latLngBounds(allCoords));
      if (boundsParts.length && boundsParts[0].isValid && boundsParts[0].isValid()) {
        try {
          var mergedBounds = boundsParts[0];
          for (var bi = 1; bi < boundsParts.length; bi++) mergedBounds.extend(boundsParts[bi]);
          map.fitBounds(mergedBounds, { padding: [24, 24] });
        } catch (e2) {}
      }

      function focusRow(row) {
        if (!row) return;
        selectedRow = row;
        updateSelected(widget, row, activeMetric);
        if (Number.isFinite(Number(row.lat)) && Number.isFinite(Number(row.lng))) {
          map.flyTo([Number(row.lat), Number(row.lng)], Math.max(map.getZoom(), 14), { duration: 0.7 });
        }
        draw();
      }

      widget.addEventListener('click', function (event) {
        var focusBtn = event.target.closest('[data-geo-focus]');
        if (!focusBtn) return;
        var row = rowsById[String(focusBtn.getAttribute('data-geo-focus'))];
        if (row) focusRow(row);
      });

      function createHouseholdMarker(point) {
        var dot = '<div class="app-geo-marker-dot" style="background:' + statusColor(point.qualification_status) + '"></div>';
        var icon = L.divIcon({
          className: 'app-geo-household-marker',
          html: '<div class="app-geo-marker">' + dot + '</div>',
          iconSize: [18, 18],
          iconAnchor: [9, 9]
        });
        var marker = L.marker([Number(point.lat), Number(point.lng)], { icon: icon });
        var actions = Array.isArray(point.pending_actions) && point.pending_actions.length
          ? point.pending_actions.map(function (action) { return '<span class="app-geo-inline-pill">' + escapeHtml(action) + '</span>'; }).join(' ')
          : '<span class="app-geo-inline-pill is-ok">Operationally ready</span>';
        var openUrl = point.detail_url || ('/harvest/modules/agri/households/view.php?id=' + encodeURIComponent(point.household_id));
        marker.bindPopup('<div class="app-geo-popup"><div class="font-black text-lg">' + escapeHtml(point.household_head_name) + '</div>'
          + '<div class="text-sm text-slate-500 mt-1">' + escapeHtml(point.barangay_name || '') + ' · ' + escapeHtml(point.household_code || 'No code') + '</div>'
          + '<div class="mt-3"><span class="app-badge ' + badgeClass(point.qualification_status) + '">' + escapeHtml(point.qualification_status || 'For Validation') + '</span></div>'
          + '<div class="mt-3 text-sm">Score: ' + escapeHtml(Number(point.score || 0).toFixed(0)) + (point.last_monitoring_date ? ' · Last monitoring: ' + escapeHtml(point.last_monitoring_date) : ' · No monitoring record yet') + '</div>'
          + '<div class="mt-3">' + actions + '</div>'
          + '<div class="mt-3"><a class="app-geo-open-link" href="' + escapeHtml(openUrl) + '">Open family record</a></div></div>');
        marker.on('click', function () {
          var row = rowsById[String(point.barangay_id)];
          if (row) {
            selectedRow = row;
            updateSelected(widget, row, activeMetric);
            draw();
          }
        });
        return marker;
      }

      function drawRoutes() {
        routeLayer.clearLayers();
        if (!toggles.routes) return;
        var batches = selectedRow && Array.isArray(selectedRow.route_batches) && selectedRow.route_batches.length
          ? selectedRow.route_batches
          : (payload.municipal_route_batches || []);
        var colors = ['#0ea5e9', '#22c55e', '#f97316', '#8b5cf6'];
        batches.forEach(function (batch, batchIndex) {
          if (!Array.isArray(batch.households) || !batch.households.length) return;
          var line = batch.households.map(function (stop) { return [Number(stop.lat), Number(stop.lng)]; });
          if (line.length > 1) {
            L.polyline(line, {
              color: colors[batchIndex % colors.length],
              weight: 4,
              opacity: 0.9,
              dashArray: '8 6'
            }).addTo(routeLayer);
          }
          batch.households.forEach(function (stop) {
            L.circleMarker([Number(stop.lat), Number(stop.lng)], {
              radius: 7,
              weight: 2,
              color: '#fff',
              fillColor: colors[batchIndex % colors.length],
              fillOpacity: 0.95
            }).bindTooltip((batch.batch_label || 'Route') + ' · Stop ' + stop.stop_order + ': ' + stop.household_head_name, {
              direction: 'top',
              offset: [0, -6]
            }).addTo(routeLayer);
          });
        });
      }

      function draw() {
        hotspotLayer.clearLayers();
        if (map.hasLayer(householdLayer)) map.removeLayer(householdLayer);
        if (zoneLayer && map.hasLayer(zoneLayer)) map.removeLayer(zoneLayer);
        if (heatLayer) {
          map.removeLayer(heatLayer);
          heatLayer = null;
        }
        if (window.L.markerClusterGroup && householdLayer.clearLayers) householdLayer.clearLayers();
        if (!window.L.markerClusterGroup && householdLayer.clearLayers) householdLayer.clearLayers();

        var values = payload.rows.map(function (row) { return valueForMetric(row, activeMetric); });
        var maxValue = Math.max.apply(null, values.concat([0]));
        var sorted = payload.rows.slice().sort(function (a, b) {
          var diff = valueForMetric(b, activeMetric) - valueForMetric(a, activeMetric);
          if (diff !== 0) return diff;
          return Number(b.attention_index || 0) - Number(a.attention_index || 0);
        });

        if (toggles.zones && payload.zones_geojson && Array.isArray(payload.zones_geojson.features) && payload.zones_geojson.features.length) {
          zoneLayer = L.geoJSON(payload.zones_geojson, {
            style: function (feature) {
              var props = feature.properties || {};
              var value = valueForMetric(props, activeMetric);
              var isSelected = selectedRow && String(props.barangay_id) === String(selectedRow.barangay_id);
              return {
                color: isSelected ? '#0f172a' : '#ffffff',
                weight: isSelected ? 3 : 1.5,
                fillColor: colorForMetric(value, maxValue, activeMetric),
                fillOpacity: isSelected ? 0.52 : 0.34
              };
            },
            onEachFeature: function (feature, layer) {
              var props = feature.properties || {};
              var row = rowsById[String(props.barangay_id)];
              layer.bindTooltip(escapeHtml(props.barangay_name || ''), { sticky: true });
              layer.on('click', function () {
                if (row) focusRow(row);
              });
            }
          }).addTo(map);
        }

        sorted.forEach(function (row) {
          if (!Number.isFinite(Number(row.lat)) || !Number.isFinite(Number(row.lng))) return;
          var value = valueForMetric(row, activeMetric);
          var radius = hotspotRadius(value, maxValue);
          var color = colorForMetric(value, maxValue, activeMetric);
          var isSelected = selectedRow && String(selectedRow.barangay_id) === String(row.barangay_id);
          var circle = L.circleMarker([Number(row.lat), Number(row.lng)], {
            radius: isSelected ? radius + 2 : radius,
            weight: 2,
            color: '#ffffff',
            fillColor: color,
            fillOpacity: isSelected ? 0.92 : 0.74
          }).addTo(hotspotLayer);
          circle.bindTooltip(escapeHtml(row.barangay_name) + ' · ' + escapeHtml(metricDefs[activeMetric].label) + ': ' + escapeHtml(numberDisplay(value, activeMetric)), {
            direction: 'top',
            offset: [0, -6]
          });
          circle.bindPopup('<div class="app-geo-popup"><div class="font-black text-lg">' + escapeHtml(row.barangay_name) + '</div><div class="text-sm text-slate-500 mt-1">' + escapeHtml(metricDefs[activeMetric].label) + ': ' + escapeHtml(numberDisplay(value, activeMetric)) + '</div><div class="mt-3 text-sm">Families: ' + escapeHtml(row.households_total || 0) + ' · Qualified: ' + escapeHtml(row.qualified_total || 0) + ' · Needs support: ' + escapeHtml(row.support_total || 0) + '</div></div>');
          circle.on('click', function () { focusRow(row); });
        });

        if (toggles.households && Array.isArray(payload.household_points) && payload.household_points.length) {
          payload.household_points.forEach(function (point) {
            if (!Number.isFinite(Number(point.lat)) || !Number.isFinite(Number(point.lng))) return;
            if (window.L.markerClusterGroup) {
              householdLayer.addLayer(createHouseholdMarker(point));
            } else {
              householdLayer.addLayer(createHouseholdMarker(point));
            }
          });
          householdLayer.addTo(map);
        }

        if (toggles.heat && window.L && typeof L.heatLayer === 'function') {
          var heatData = payload.rows.filter(function (row) {
            return Number.isFinite(Number(row.lat)) && Number.isFinite(Number(row.lng));
          }).map(function (row) {
            var value = valueForMetric(row, activeMetric);
            var intensity = maxValue > 0 ? Math.max(0.15, value / maxValue) : 0.15;
            return [Number(row.lat), Number(row.lng), intensity];
          });
          heatLayer = L.heatLayer(heatData, {
            radius: 28,
            blur: 22,
            maxZoom: 17,
            gradient: { 0.25: '#38bdf8', 0.45: '#22c55e', 0.65: '#eab308', 0.85: '#f97316', 1.0: '#dc2626' }
          }).addTo(map);
        }

        drawRoutes();
        if (rankingEl) rankingEl.innerHTML = rankingMarkup(sorted, activeMetric);
        if (rankedByEl) rankedByEl.textContent = 'Ranked by ' + metricDefs[activeMetric].label.toLowerCase();
        if (noteText) noteText.textContent = metricDefs[activeMetric].label + ' layer · ' + metricDefs[activeMetric].legend;
        if (layerNote) {
          layerNote.textContent = (toggles.zones ? 'Zones on' : 'Zones off')
            + ' · ' + (toggles.households ? 'markers on' : 'markers off')
            + ' · ' + (toggles.routes ? 'routes on' : 'routes off')
            + ' · ' + (toggles.heat ? 'heatmap on' : 'heatmap off');
        }
        if (dataNote && payload.notes) dataNote.textContent = payload.notes;
        updateSelected(widget, selectedRow || sorted[0], activeMetric);
      }

      if (!selectedRow && payload.rows[0]) selectedRow = payload.rows[0];
      updateSelected(widget, selectedRow, activeMetric);
      draw();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var widgets = window.HARVEST_GEO_WIDGETS || [];
    widgets.forEach(function (config) {
      var datasetScript = document.getElementById(config.id + '-data');
      var widget = datasetScript ? datasetScript.closest('[data-geo-dashboard]') : null;
      if (widget) initWidget(widget, config);
    });
  });
})();
