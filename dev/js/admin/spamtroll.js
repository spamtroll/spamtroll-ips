/**
 * Spamtroll Admin JavaScript
 *
 * @package     IPS Community Suite
 * @subpackage  Spamtroll Anti-Spam
 */

/**
 * Renders an icon and a message into a container.
 *
 * The message comes back from the API over the network, so it goes in as
 * text. It used to be concatenated into innerHTML, which is a stored XSS
 * hole one compromised or misconfigured API response wide, in the AdminCP
 * of all places.
 */
function setResult(container, icon, className, message) {
    container.textContent = '';

    var wrapper = document.createElement('span');
    if (className) { wrapper.className = className; }

    var glyph = document.createElement('i');
    glyph.className = icon === 'spinner' ? 'fa fa-spinner fa-spin' : 'fa fa-' + icon;
    wrapper.appendChild(glyph);
    wrapper.appendChild(document.createTextNode(' ' + message));

    container.appendChild(wrapper);
}

document.addEventListener('DOMContentLoaded', function() {

    /* Test Connection Handler */
    var testBtn = document.getElementById('spamtrollTestConnection');
    var resultSpan = document.getElementById('spamtrollTestResult');

    if (testBtn && resultSpan) {
        testBtn.addEventListener('click', function() {
            var testUrl = testBtn.getAttribute('data-test-url');
            var testingText = testBtn.getAttribute('data-testing-text') || 'Testing...';

            setResult(resultSpan, 'spinner', '', testingText);
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
                setResult(
                    resultSpan,
                    data.success ? 'check' : 'times',
                    data.success ? 'ipsType_success' : 'ipsType_warning',
                    data.message
                );
                testBtn.disabled = false;
            })
            .catch(function() {
                setResult(resultSpan, 'times', 'ipsType_warning', 'Connection error');
                testBtn.disabled = false;
            });
        });
    }

    /* Clipboard handler for the submission-UUID copy button.
     *
     * Delegated, so it survives the table re-rendering through AJAX
     * filtering. It used to be a <script> block built by string
     * concatenation in modules/admin/spamtroll/logs.php — inline script in
     * the AdminCP, which the Content-Security-Policy is entitled to refuse. */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.spamtroll-copy-btn') : null;
        if (!btn) { return; }
        e.preventDefault();

        var value = btn.getAttribute('data-clipboard') || '';
        var copiedLabel = btn.getAttribute('data-copied-label') || 'Copied';

        var done = function () {
            var original = btn.getAttribute('data-original-label');
            if (original === null) {
                original = btn.textContent;
                btn.setAttribute('data-original-label', original);
            }
            btn.textContent = copiedLabel;
            setTimeout(function () { btn.textContent = original; }, 1200);
        };

        var fallback = function () {
            var ta = document.createElement('textarea');
            ta.value = value;
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (err) { /* nothing else to try */ }
            ta.remove();
            done();
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done).catch(fallback);
        } else {
            fallback();
        }
    });

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
