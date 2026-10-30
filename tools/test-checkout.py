"""Local-only HTTP/session acceptance; synthetic contacts, never real mail."""
import http.cookiejar
import re
import unittest
import hashlib
import subprocess
import time
from urllib.error import HTTPError
from urllib.request import build_opener, HTTPCookieProcessor, Request
from urllib.parse import urlencode
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE = 'http://127.0.0.1:8081'

class Checkout(unittest.TestCase):
    def setUp(self):
        self.http = build_opener(HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.headers = {}
        self.before_captures = set((ROOT/'local-dev/private/mail-captures').glob('*.txt'))
        self.page = self.get('/poisk?q=3B-8489')
        self.csrf = self.field(self.page, '_csrf')
        self.product = self.field(self.page, 'product_id')
        sku = re.search(r'class="cp-card__sku"[^>]*>([^<]+)', self.page)
        self.sku = sku.group(1).strip() if sku else ''
        self.assertTrue(self.csrf and self.product, 'search must expose a real product add form')

    def get(self, path):
        return self.http.open(BASE + path).read().decode()

    def post(self, path, **data):
        data.setdefault('_csrf', self.csrf)
        return self.http.open(Request(BASE + path, urlencode(data).encode(), self.headers)).read().decode()

    def field(self, html, name):
        match = re.search(r'name="' + name + r'"[^>]*value="([^"]*)"', html)
        return match.group(1) if match else ''

    def add(self):
        return self.post('/korzina/add', product_id=self.product, qty=2)

    def order(self, page, **extra):
        data = dict(name='Synthetic acceptance', phone='70000000000', email='acceptance@example.invalid', consent=1,
                    checkout_token=self.field(page, 'checkout_token'))
        data.update(extra)
        return self.post('/korzina/checkout', **data)

    def captured(self):
        files = set((ROOT/'local-dev/private/mail-captures').glob('*.txt')) - self.before_captures
        bodies = [path.read_text(encoding='utf-8') for path in files]
        for body in bodies:
            self.assertTrue(body.startswith('To: info@cmteh.ru\n'), 'all captures must use the new recipient')
        return bodies

    def test_brand_metadata(self):
        page = self.get('/kontakty')
        self.assertNotIn('info@cat-part.ru', page)
        self.assertIn('info@cmteh.ru', page)

    def test_badge_and_quantity(self):
        page = self.add()
        self.assertIn('cp-cart__badge">2<', page)
        page = self.post('/korzina/update', product_id=self.product, qty=999)
        self.assertIn('cp-cart__badge">99<', page)
        page = self.post('/korzina/update', product_id=self.product, qty=-3)
        self.assertIn('Корзина пуста', page)

    def test_honeypot(self):
        page = self.add()
        result = self.order(page, website='spam.invalid')
        self.assertNotIn('Запрос принят.', result)
        self.assertIn('cp-cart__badge">2<', result)
        self.assertEqual(len(self.captured()), 0)

    def test_failed_transport_and_csrf(self):
        page = self.add()
        with self.assertRaises(HTTPError) as bad:
            self.post('/korzina/checkout', _csrf='invalid')
        self.assertEqual(bad.exception.code, 419)
        self.headers = {'X-Test-Fail-Transport': '1'}
        result = self.order(page)
        self.assertTrue('Не удалось отправить запрос' in result)
        self.assertTrue('cp-cart__badge">2<' in result)
        self.assertEqual(len(self.captured()), 0)
        self.headers = {}
        self.assertTrue('Запрос принят.' in self.order(result))
        self.assertEqual(len(self.captured()), 1)

    def test_lead_capture(self):
        result = self.post('/zayavka', name='Synthetic lead', phone='70000000000', consent=1)
        self.assertTrue('письмо не отправлялось' in result)
        self.assertEqual(len(self.captured()), 1)

    def test_z_rate_across_sessions(self):
        self.headers = {'X-Test-Throttle': '1'}
        for attempt in range(6):
            # A new session must not bypass the shared IP budget.
            self.http = build_opener(HTTPCookieProcessor(http.cookiejar.CookieJar()))
            self.csrf = self.field(self.get('/poisk?q=3B-8489'), '_csrf')
            page = self.add()
            result = self.order(page, name='Synthetic ' + str(attempt))
            self.assertEqual('Запрос принят.' in result, attempt < 5)
            if attempt == 5:
                self.assertTrue('Слишком много запросов' in result)
                self.assertTrue('cp-cart__badge">2<' in result)
        self.assertEqual(len(self.captured()), 5)

    def test_new_order_and_replay(self):
        page = self.add()
        self.assertTrue(self.field(page, 'checkout_token'), 'one-time checkout token required')
        self.assertIn('Запрос принят.', self.order(page))
        self.assertNotIn('Запрос принят.', self.order(page))
        fresh = self.add()
        self.assertNotEqual(self.field(page, 'checkout_token'), self.field(fresh, 'checkout_token'))
        self.assertIn('Запрос принят.', self.order(fresh))
        bodies = self.captured()
        self.assertEqual(len(bodies), 2)
        self.assertTrue(self.sku and all(self.sku in body and '× 2' in body for body in bodies))

    def test_private_capture(self):
        self.assertFalse((ROOT / 'local-dev/site/storage/order-captures').exists(), 'PII capture must be outside docroot')

    def test_product_claim(self):
        links = re.findall(r'href="([^"]+)"', self.page)
        product = next((x for x in links if '/product/' in x or '/tovar/' in x), '')
        if product.startswith(BASE): product = product[len(BASE):]
        if product:
            self.assertNotIn('Отгрузка со склада', self.get(product))
        else:
            self.assertNotIn('Отгрузка со склада', (ROOT / 'local-dev/site/app/Views/catalog/product.php').read_text(encoding='utf-8'))

    def test_home_articles(self):
        self.assertEqual(self.get('/').count('<article class="cp-post">'), 3)

if __name__ == '__main__':
    # Concise failures avoid dumping pages or any captured personal data.
    unittest.TestCase.maxDiff = 120
    limits = ROOT / 'local-dev/private/rate-limits'
    state = [limits / ('checkout-' + hashlib.sha256(ip.encode()).hexdigest() + '.json') for ip in ['192.0.2.250', '192.0.2.251']]
    for path in state: path.unlink(missing_ok=True)
    server = subprocess.Popen([str(ROOT/'local-dev/bin/php/php.exe'), '-S', '127.0.0.1:8081', '-t', str(ROOT/'local-dev/site'), str(ROOT/'tools/checkout-test-router.php')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        time.sleep(.5)
        unittest.main(verbosity=2)
    finally:
        server.terminate()
        server.wait(timeout=5)
        for path in state: path.unlink(missing_ok=True)
