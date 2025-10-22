(() => {
    const presetSelect = document.getElementById('presetSelect');
    const tableSelect = document.getElementById('tableSelect');
    const groupBySelect = document.getElementById('groupBySelect');
    const metricFuncSelect = document.getElementById('metricFuncSelect');
    const metricColSelect = document.getElementById('metricColSelect');
    const chartTypeSelect = document.getElementById('chartTypeSelect');
    const rawMode = document.getElementById('rawMode');
    const rawColumnsSelect = document.getElementById('rawColumnsSelect');
    const rawSelectAll = document.getElementById('rawSelectAll');
    const rawAllWrap = document.getElementById('rawAllWrap');
    const rawLimit = document.getElementById('rawLimit');
    const rawSortCol = document.getElementById('rawSortCol');
    const rawSortDir = document.getElementById('rawSortDir');
    const rawPrev = document.getElementById('rawPrev');
    const rawNext = document.getElementById('rawNext');
    const rawPageInfo = document.getElementById('rawPageInfo');
    const filtersBox = document.getElementById('filters');
    const refreshBtn = document.getElementById('refreshBtn');
    const exportCsvBtn = document.getElementById('exportCsvBtn');
    const resetBtn = document.getElementById('resetBtn');
    const kpiContainer = document.getElementById('kpiContainer');
    const alertBox = document.getElementById('alertBox');
    const loading = document.getElementById('loading');
    const sqlBox = document.getElementById('sqlBox');
    const sqlText = document.getElementById('sqlText');
    const copySql = document.getElementById('copySql');
    const chartEl = document.getElementById('chart');
    const tableBox = document.getElementById('table');
    const tableHead = document.getElementById('tableHead');
    const tableBody = document.getElementById('tableBody');

    if (!presetSelect || !tableSelect || !groupBySelect || !metricFuncSelect || !metricColSelect || !chartTypeSelect || !refreshBtn || !kpiContainer || !chartEl || !tableBox || !tableHead || !tableBody) {
        console.error('Required DOM elements are missing.');
        return;
    }

	let chart = echarts.init(chartEl, null, {renderer: 'canvas'});

    function showAlert(msg){ if(!alertBox) return; alertBox.hidden=false; alertBox.textContent=msg; }
    function clearAlert(){ if(!alertBox) return; alertBox.hidden=true; alertBox.textContent=''; }
    function setLoading(v){ if(!loading) return; loading.hidden=!v; }
    function showSQL(sql){ if(sqlBox && sqlText){ sqlText.textContent = sql || ''; sqlBox.hidden = !sql; } }

    function prettifyName(name){
        if (!name) return '';
        const s = String(name).replace(/_/g,' ');
        return s.replace(/\w\S*/g, (w)=> w.charAt(0).toUpperCase()+w.slice(1));
    }

    let lastResultRows = [];
    function setLastRows(rows){ lastResultRows = Array.isArray(rows) ? rows : []; }
    function exportCSV(){
        if (!lastResultRows.length) { showAlert('No data to export. Run a query first.'); return; }
        const cols = Object.keys(lastResultRows[0]);
        const escape = (v)=>{
            if (v === null || v === undefined) return '';
            const s = String(v);
            if (/[",\n]/.test(s)) return '"' + s.replace(/"/g,'""') + '"';
            return s;
        };
        const lines = [cols.join(',')].concat(lastResultRows.map(r=> cols.map(c=>escape(r[c])).join(',')));
        const blob = new Blob([lines.join('\n')], {type:'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'export.csv';
        document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    }

    async function fetchJSON(url, options) {
        setLoading(true); clearAlert();
        const res = await fetch(url, options).catch(err=>{ setLoading(false); throw new Error('Network error: '+err.message); });
		if (!res.ok) {
			let msg='';
            try { msg = (await res.text()).slice(0,400); } catch(e) {}
            setLoading(false);
            throw new Error(`HTTP ${res.status}: ${msg}`);
		}
        const data = await res.json();
        setLoading(false);
        return data;
	}

    function setOptions(select, items, {multiple=false, placeholder=''}={}){
        select.innerHTML='';
        if (!multiple && placeholder) {
            const opt = document.createElement('option');
            opt.value=''; opt.textContent=placeholder; select.appendChild(opt);
        }
        (Array.isArray(items) ? items : []).filter(Boolean).forEach(it=>{
            const opt = document.createElement('option');
            if (typeof it === 'string') {
                opt.value = it;
                opt.textContent = it;
            } else if (it && typeof it === 'object' && 'value' in it) {
                opt.value = String(it.value ?? '');
                opt.textContent = String((it.label ?? it.value) ?? '');
            } else {
                const text = String(it);
                opt.value = text;
                opt.textContent = text;
            }
            select.appendChild(opt);
        });
    }

	async function loadTables(){
		try {
			const data = await fetchJSON('analytics-api/tables.php');
            const tables = (Array.isArray(data.tables) ? data.tables : []).filter(Boolean).map(name=>String(name));
            setOptions(tableSelect, tables, {placeholder:'Select table'});
		} catch (e) {
            showAlert('Failed to load tables: '+e.message);
		}
	}

	function loadPresets(){
		const presets = [
			{ value: '', label: 'Presets (alumni_portal)' },
			{ value: 'users_by_role', label: 'Users by Role (COUNT)' },
			{ value: 'users_by_batch', label: 'Users by Batch (COUNT)' },
			{ value: 'news_by_category', label: 'News by Category (COUNT)' },
			{ value: 'jobs_by_status', label: 'Jobs by Status (COUNT)' },
			{ value: 'events_by_type', label: 'Events by Type (COUNT)' },
			{ value: 'rsvps_by_status', label: 'RSVPs by Status (COUNT)' },
			{ value: 'jobs_by_company', label: 'Jobs by Company (COUNT)' },
			{ value: 'notices_views', label: 'Top Notices by Views (SUM)' },
		];
		setOptions(presetSelect, presets);
	}

    let columnMeta = [];
    async function loadColumns(){
        if (!tableSelect) return;
        const tbl = tableSelect.value;
		if (!tbl) return;
		try {
			const data = await fetchJSON('analytics-api/columns.php?table='+encodeURIComponent(tbl));
            columnMeta = Array.isArray(data.columns) ? data.columns : [];
            const cols = columnMeta.map(c=>c.name);
            const colOpts = cols.map(c=>({value:c,label:prettifyName(c)}));
            setOptions(groupBySelect, colOpts, {multiple:true});
            setOptions(metricColSelect, [{value:'*',label:'* (all)'}].concat(colOpts), {});
            setOptions(rawColumnsSelect, colOpts, {multiple:true});
            setOptions(rawSortCol, [{value:'',label:'Sort by'}].concat(colOpts), {});
            rawColumnsSelect.hidden = !(rawMode && rawMode.checked);
            rawLimit.hidden = !(rawMode && rawMode.checked);
            rawAllWrap.hidden = !(rawMode && rawMode.checked);
            rawSortCol.hidden = !(rawMode && rawMode.checked);
            rawSortDir.hidden = !(rawMode && rawMode.checked);
            rawPrev.hidden = !(rawMode && rawMode.checked);
            rawNext.hidden = !(rawMode && rawMode.checked);
            rawPageInfo.hidden = !(rawMode && rawMode.checked);
            filtersBox.hidden = false;
        } catch (e) {
            showAlert('Failed to load columns: '+e.message);
		}
	}

	function renderKPIs(rows){
		kpiContainer.innerHTML='';
		const totals = Object.keys(rows[0] || {}).filter(k=>/^(count|sum|avg|max|min)_/i.test(k));
		totals.slice(0,4).forEach(key=>{
			const card = document.createElement('div');
			card.className='kpi';
			card.innerHTML = `<div class="label">${key}</div><div class="value">${rows[0][key]}</div>`;
			kpiContainer.appendChild(card);
		});
	}

	function showTable(rows){
		tableBox.hidden=false; chartEl.hidden=true;
		tableHead.innerHTML=''; tableBody.innerHTML='';
		if (!rows.length) return;
		const cols = Object.keys(rows[0]);
		const tr = document.createElement('tr');
		cols.forEach(c=>{ const th=document.createElement('th'); th.textContent=c; tr.appendChild(th); });
		tableHead.appendChild(tr);
		rows.forEach(r=>{
			const trb = document.createElement('tr');
			cols.forEach(c=>{ const td=document.createElement('td'); td.textContent = r[c]; trb.appendChild(td); });
			tableBody.appendChild(trb);
		});
	}

	function showChart(type, rows, groupBys){
		tableBox.hidden=true; chartEl.hidden=false;
		chart.resize();
		if (!rows.length){ chart.clear(); return; }
		const dims = Object.keys(rows[0]);
		const metricKeys = dims.filter(k=>/^(count|sum|avg|max|min)_/i.test(k));
		const categoryKey = groupBys[0] || dims.find(d=>!metricKeys.includes(d)) || dims[0];
		const categories = rows.map(r=>r[categoryKey]);
		let option = {tooltip:{},legend:{},xAxis:{type:'category',data:categories},yAxis:{type:'value'},series:[]};
		switch(type){
			case 'bar':
				option.series = metricKeys.map(k=>({name:k,type:'bar',data:rows.map(r=>r[k])}));
				break;
			case 'stackedBar':
				option.series = metricKeys.map(k=>({name:k,type:'bar',stack:'total',data:rows.map(r=>r[k])}));
				break;
			case 'line':
				option.series = metricKeys.map(k=>({name:k,type:'line',data:rows.map(r=>r[k])}));
				break;
			case 'area':
				option.series = metricKeys.map(k=>({name:k,type:'line',areaStyle:{},data:rows.map(r=>r[k])}));
				break;
			case 'stackedArea':
				option.series = metricKeys.map(k=>({name:k,type:'line',areaStyle:{},stack:'total',data:rows.map(r=>r[k])}));
				break;
			case 'pie':
				option = {tooltip:{},legend:{},series:[{type:'pie',data:rows.map(r=>({name:r[categoryKey],value:metricKeys.length? r[metricKeys[0]]:1}))}]};
				break;
			case 'donut':
				option = {tooltip:{},legend:{},series:[{type:'pie',radius:['40%','70%'],data:rows.map(r=>({name:r[categoryKey],value:metricKeys.length? r[metricKeys[0]]:1}))}]};
				break;
			case 'scatter':
				if (metricKeys.length < 2) { metricKeys.push(metricKeys[0]); }
				option = {tooltip:{},xAxis:{},yAxis:{},series:[{type:'scatter',data:rows.map(r=>[r[metricKeys[0]], r[metricKeys[1]]])}]};
				break;
			case 'heatmap':
				const m1 = metricKeys[0] || dims[0];
				const m2 = metricKeys[1] || dims[1] || m1;
				option = {tooltip:{},xAxis:{type:'category',data:categories},yAxis:{type:'category',data:[m1,m2]},visualMap:{min:0,max:Math.max(...rows.map(r=>r[m1]||0,r[m2]||0)),calculable:true},series:[{type:'heatmap',data:rows.flatMap((r,i)=>[[i,0,r[m1]||0],[i,1,r[m2]||0]])}]};
				break;
			default:
				showTable(rows); return;
		}
		chart.setOption(option, true);
	}

    let rawPage = 1;
    function updatePageInfo(){ if (rawPageInfo) rawPageInfo.textContent = 'Page ' + rawPage; }

    function getFilters(){
        if (!filtersBox) return [];
        const rows = Array.from(filtersBox.querySelectorAll('.filter-row'));
        const filters = [];
        for (const row of rows){
            const col = row.querySelector('.filter-col')?.value || '';
            const op = row.querySelector('.filter-op')?.value || '';
            const v1 = row.querySelector('.filter-val')?.value || '';
            const v2 = row.querySelector('.filter-val2')?.value || '';
            if (!col || !op) continue;
            filters.push({col,op,value:v1,value2:v2});
        }
        return filters;
    }

    function ensureFilterRowOptions(){
        const opts = Array.from(rawColumnsSelect?.options||[]).map(o=>o.value);
        filtersBox.querySelectorAll('.filter-col').forEach(sel=>{
            setOptions(sel, [{value:'',label:'Field'}].concat(opts.map(c=>({value:c}))), {});
        });
    }
    async function runQuery(){
        if (!tableSelect) return;
        const table = tableSelect.value;
		if (!table) return;
        const groupBy = Array.from(groupBySelect.selectedOptions).map(o=>o.value);
        let metricColumn = metricColSelect.value || '*';
        if (metricFuncSelect.value !== 'COUNT' && metricColumn === '*') {
            // force user to pick a column for non-COUNT aggregations
            showAlert('Please choose a metric column (not * ) for ' + metricFuncSelect.value + '.');
            return;
        }
        const metric = {fn: metricFuncSelect.value, column: metricColumn};
        const isRaw = !!(rawMode && rawMode.checked);
        const selectedRawCols = Array.from(rawColumnsSelect?.selectedOptions || []).map(o=>o.value);
        const limitVal = Math.max(1, Math.min(10000, parseInt(rawLimit?.value || '200', 10)));
        const sortColVal = rawSortCol?.value || '';
        const sortDirVal = (rawSortDir?.value || 'asc').toLowerCase();
        const offsetVal = isRaw ? (rawPage - 1) * limitVal : 0;
        // No extra validation needed here; COUNT(*) without group-by is allowed
        const filters = getFilters();
        const body = isRaw ? {table, raw:true, columns: selectedRawCols, limit: limitVal, offset: offsetVal, sortCol: sortColVal, sortDir: sortDirVal, filters} : {table, groupBy, metrics:[metric], filters};
		try {
			const data = await fetchJSON('analytics-api/data.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)});
            if (isRaw) {
                renderKPIs([]);
                showTable(data.rows);
                setLastRows(data.rows);
                updatePageInfo();
                showSQL(data.sql);
            } else {
                renderKPIs(data.rows);
                showChart(chartTypeSelect.value, data.rows, groupBy);
                setLastRows(data.rows);
                showSQL(data.sql);
            }
        } catch (e) {
            showAlert('Query failed: '+e.message);
		}
	}

	window.addEventListener('resize', ()=> chart.resize());

	presetSelect.addEventListener('change', ()=>{
		const v = presetSelect.value;
		const mapping = {
			users_by_role: { table:'users', groupBy:['role'], metric:{fn:'COUNT', column:'*'}, chart:'bar' },
			users_by_batch: { table:'users', groupBy:['batch'], metric:{fn:'COUNT', column:'*'}, chart:'bar' },
			news_by_category: { table:'news_posts', groupBy:['category'], metric:{fn:'COUNT', column:'*'}, chart:'donut' },
			jobs_by_status: { table:'job_opportunities', groupBy:['status'], metric:{fn:'COUNT', column:'*'}, chart:'bar' },
			events_by_type: { table:'events', groupBy:['event_type'], metric:{fn:'COUNT', column:'*'}, chart:'pie' },
			rsvps_by_status: { table:'event_rsvps', groupBy:['status'], metric:{fn:'COUNT', column:'*'}, chart:'bar' },
			jobs_by_company: { table:'job_opportunities', groupBy:['company'], metric:{fn:'COUNT', column:'*'}, chart:'bar' },
			notices_views: { table:'notices', groupBy:['title'], metric:{fn:'SUM', column:'views'}, chart:'bar' },
		};
		if (!v || !mapping[v]) return;
		const m = mapping[v];
		tableSelect.value = m.table;
		loadColumns().then(()=>{
			Array.from(groupBySelect.options).forEach(o=> o.selected = m.groupBy.includes(o.value));
			metricFuncSelect.value = m.metric.fn;
			metricColSelect.value = m.metric.column === '*' ? '*' : m.metric.column;
			chartTypeSelect.value = m.chart;
			runQuery();
		});
	});

    tableSelect.addEventListener('change', loadColumns);
    rawMode?.addEventListener('change', ()=>{
        const v = !!rawMode.checked;
        rawColumnsSelect.hidden = !v; rawLimit.hidden = !v; rawAllWrap.hidden = !v;
        rawSortCol.hidden = !v; rawSortDir.hidden = !v; rawPrev.hidden = !v; rawNext.hidden = !v; rawPageInfo.hidden = !v;
        rawPage = 1; updatePageInfo();
    });
    rawSelectAll?.addEventListener('change', ()=>{
        const sel = !!rawSelectAll.checked;
        Array.from(rawColumnsSelect.options).forEach(o=> o.selected = sel);
    });
    metricFuncSelect?.addEventListener('change', ()=>{
        const fn = metricFuncSelect.value;
        if (fn === 'COUNT') return;
        const current = metricColSelect.value || '*';
        if (current && current !== '*') return;
        const numericTypes = ['int','integer','bigint','smallint','tinyint','mediumint','decimal','numeric','float','double','real'];
        const firstNumeric = (columnMeta||[]).find(c=> numericTypes.includes(String(c.data_type||'').toLowerCase()));
        if (firstNumeric && metricColSelect.querySelector(`option[value="${firstNumeric.name}"]`)) {
            metricColSelect.value = firstNumeric.name;
        }
    });
    rawPrev?.addEventListener('click', ()=>{ if (rawPage > 1) { rawPage--; runQuery(); } });
    rawNext?.addEventListener('click', ()=>{ rawPage++; runQuery(); });

    // Filter interactions
    filtersBox?.addEventListener('change', (e)=>{
        const row = e.target.closest('.filter-row');
        if (!row) return;
        if (e.target.classList.contains('filter-op')){
            const op = e.target.value;
            const val2 = row.querySelector('.filter-val2');
            if (val2) val2.style.display = (op === 'between') ? '' : 'none';
        }
    });
    filtersBox?.addEventListener('click', (e)=>{
        if (e.target.classList.contains('filter-add')){
            const clone = filtersBox.querySelector('.filter-row').cloneNode(true);
            clone.querySelectorAll('input').forEach(i=> i.value = '');
            filtersBox.appendChild(clone);
            ensureFilterRowOptions();
        }
    });

    // Initialize filter fields once columns load
    const origLoadColumns = loadColumns;
    loadColumns = async function(){
        await origLoadColumns();
        ensureFilterRowOptions();
    }
    refreshBtn.addEventListener('click', runQuery);
    exportCsvBtn?.addEventListener('click', exportCSV);
    resetBtn?.addEventListener('click', ()=>{
        // Reset selects and inputs
        presetSelect.value = '';
        tableSelect.value = '';
        groupBySelect.selectedIndex = -1;
        metricFuncSelect.value = 'COUNT';
        metricColSelect.value = '*';
        chartTypeSelect.value = 'table';
        rawMode.checked = false;
        rawColumnsSelect.hidden = true; rawLimit.hidden = true; rawAllWrap.hidden = true;
        rawSortCol.hidden = true; rawSortDir.hidden = true; rawPrev.hidden = true; rawNext.hidden = true; rawPageInfo.hidden = true;
        rawSelectAll.checked = false;
        rawColumnsSelect.selectedIndex = -1;
        rawLimit.value = '200';
        rawSortCol.value = '';
        rawSortDir.value = 'asc';
        // Clear filters to a single empty row
        if (filtersBox){
            const first = filtersBox.querySelector('.filter-row');
            if (first){
                filtersBox.innerHTML = '';
                filtersBox.appendChild(first);
                first.querySelectorAll('input').forEach(i=> i.value='');
                first.querySelectorAll('select').forEach(s=>{ if (s.classList.contains('filter-op')) s.value='eq'; else s.value=''; });
                const val2 = first.querySelector('.filter-val2'); if (val2) val2.style.display='none';
            }
        }
        // Clear outputs
        kpiContainer.innerHTML = '';
        tableHead.innerHTML = '';
        tableBody.innerHTML = '';
        chart.clear();
        showSQL('');
        clearAlert();
    });
    copySql?.addEventListener('click', async ()=>{
        try { await navigator.clipboard.writeText(sqlText?.textContent || ''); showAlert('SQL copied.'); setTimeout(clearAlert, 1000); } catch(e){ showAlert('Failed to copy SQL'); }
    });

    loadTables().then(()=>{ loadPresets(); });
})();

