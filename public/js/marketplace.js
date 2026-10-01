(function () {
  'use strict';

  var calculator = document.querySelector('[data-bid-calculator]');
  if (calculator) {
    var amountInput = calculator.querySelector('input[name="amount"]');
    var paymentTotal = calculator.querySelector('[data-payment-total]');
    var newTotal = calculator.querySelector('[data-new-total]');
    var projectedRank = calculator.querySelector('[data-projected-rank]');
    var currentCents = Number(calculator.dataset.currentCents || 0);
    var competingTotals = [];

    try {
      competingTotals = JSON.parse(calculator.dataset.competingTotals || '[]');
    } catch (_error) {
      competingTotals = [];
    }

    var money = function (cents) {
      return '$' + (cents / 100).toFixed(2);
    };

    var updateProjection = function () {
      var additionalCents = Math.max(0, Math.round(Number(amountInput.value || 0) * 100));
      var combinedCents = currentCents + additionalCents;
      var rank = 1 + competingTotals.filter(function (total) { return Number(total) >= combinedCents; }).length;
      paymentTotal.textContent = money(additionalCents);
      newTotal.textContent = money(combinedCents);
      projectedRank.textContent = '#' + rank;
    };

    amountInput.addEventListener('input', updateProjection);
    updateProjection();
  }

  document.querySelectorAll('[data-destination-form]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var destination = form.querySelector('input[name="url"]');
      var platform = form.querySelector('select[name="platform"]');
      var value = destination ? destination.value.trim() : '';
      if (destination && value && !platform.value && !value.startsWith('@') && !/^[a-z][a-z0-9+.-]*:\/\//i.test(value)) {
        destination.value = 'https://' + value;
      }
    });
  });

  var listingView = document.querySelector('[data-listing-view]');
  if (listingView) {
    var card = listingView.querySelector('[data-listing-card]');
    var empty = listingView.querySelector('[data-listing-empty]');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var fields = {
      name: listingView.querySelector('[data-detail-name]'),
      category: listingView.querySelector('[data-detail-category]'),
      short: listingView.querySelector('[data-detail-short]'),
      description: listingView.querySelector('[data-detail-description]'),
      url: listingView.querySelector('[data-detail-url]'),
      go: listingView.querySelector('[data-detail-go]'),
      rank: listingView.querySelector('[data-detail-rank]'),
      views: listingView.querySelector('[data-detail-views]'),
      clicks: listingView.querySelector('[data-detail-clicks]'),
      total: listingView.querySelector('[data-detail-total]'),
      sample: listingView.querySelector('[data-detail-sample]'),
      report: listingView.querySelector('[data-detail-report]')
    };

    document.querySelectorAll('[data-inline-listing] .listing-select').forEach(function (link) {
      link.addEventListener('click', function (event) {
        var row = link.closest('[data-inline-listing]');
        if (!row) return;
        event.preventDefault();

        fields.name.textContent = row.dataset.name || '';
        fields.category.textContent = row.dataset.category || '';
        fields.short.textContent = row.dataset.short || '';
        fields.description.textContent = row.dataset.description || row.dataset.short || '';
        fields.url.textContent = row.dataset.url || '';
        if (row.dataset.reviewSample === '1') {
          fields.url.removeAttribute('href');
          fields.go.hidden = true;
          fields.go.removeAttribute('href');
        } else {
          fields.url.href = row.dataset.goUrl || '#';
          fields.go.href = row.dataset.goUrl || '#';
          fields.go.hidden = false;
        }
        fields.rank.textContent = row.dataset.rank || '—';
        fields.views.textContent = Number(row.dataset.viewCount || 0).toLocaleString();
        fields.clicks.textContent = Number(row.dataset.clickCount || 0).toLocaleString();
        fields.total.textContent = row.dataset.total || '';
        fields.sample.hidden = row.dataset.reviewSample !== '1';
        fields.report.action = row.dataset.reportUrl || '#';
        card.hidden = false;
        empty.hidden = true;
        listingView.classList.remove('is-empty');
        document.querySelectorAll('[data-inline-listing]').forEach(function (item) { item.classList.remove('selected'); });
        row.classList.add('selected');
        window.history.pushState({}, '', row.dataset.homeUrl || link.href);
        listingView.scrollIntoView({ behavior: 'smooth', block: 'start' });

        fetch(row.dataset.viewUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf ? csrf.content : ''
          }
        }).then(function (response) { return response.ok ? response.json() : {}; }).then(function (payload) {
          if (payload.recorded === true) {
            var nextViews = Number(row.dataset.viewCount || 0) + 1;
            row.dataset.viewCount = String(nextViews);
            fields.views.textContent = nextViews.toLocaleString();
            var rowView = row.querySelector('.views');
            if (rowView) rowView.textContent = nextViews.toLocaleString();
          }
        }).catch(function () {});
      });
    });
  }
}());
