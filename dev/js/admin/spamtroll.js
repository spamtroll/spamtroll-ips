/**
 * Spamtroll Admin JavaScript
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 */

document.addEventListener('DOMContentLoaded', function() {

    /* Test Connection Handler */
    var testBtn = document.getElementById('spamtrollTestConnection');
    var resultSpan = document.getElementById('spamtrollTestResult');

    if (testBtn && resultSpan) {
        testBtn.addEventListener('click', function() {
            var testUrl = testBtn.getAttribute('data-test-url');
            var testingText = testBtn.getAttribute('data-testing-text') || 'Testing...';

            resultSpan.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + testingText;
            testBtn.disabled = true;

            var apiKey = document.querySelector('[name="spamtroll_api_key"]');
            var apiUrl = document.querySelector('[name="spamtroll_api_url"]');

            var params = new URLSearchParams();
            if (typeof ips !== 'undefined') {
                params.append('csrfKey', ips.getSetting('csrfKey'));
            }
            if (apiKey) params.append('api_key', apiKey.value);
            if (apiUrl) params.append('api_url', apiUrl.value);

            fetch(testUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    resultSpan.innerHTML = '<span class="ipsType_success"><i class="fa fa-check"></i> ' + data.message + '</span>';
                } else {
                    resultSpan.innerHTML = '<span class="ipsType_warning"><i class="fa fa-times"></i> ' + data.message + '</span>';
                }
                testBtn.disabled = false;
            })
            .catch(function(error) {
                resultSpan.innerHTML = '<span class="ipsType_warning"><i class="fa fa-times"></i> Connection error</span>';
                testBtn.disabled = false;
            });
        });
    }

    /* Chart initialization */
    var chartCanvas = document.getElementById('spamtrollChart');
    if (chartCanvas && typeof Chart !== 'undefined') {
        var ctx = chartCanvas.getContext('2d');
        var labels = JSON.parse(chartCanvas.getAttribute('data-labels') || '[]');
        var totalData = JSON.parse(chartCanvas.getAttribute('data-total') || '[]');
        var blockedData = JSON.parse(chartCanvas.getAttribute('data-blocked') || '[]');
        var totalLabel = chartCanvas.getAttribute('data-label-total') || 'Total';
        var blockedLabel = chartCanvas.getAttribute('data-label-blocked') || 'Blocked';

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: totalLabel,
                    data: totalData,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    fill: true,
                    tension: 0.3
                }, {
                    label: blockedLabel,
                    data: blockedData,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

});
