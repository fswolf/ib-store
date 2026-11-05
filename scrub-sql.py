#!/usr/bin/env python3
# Turn a phpMyAdmin export of the live store into the install file for the repo: ./scrub-sql.py export.sql > ib_store.sql
# Keeps the schema, the categories, products and product images (the demo catalogue). Drops every row that is
# about a person or a sale, blanks the store's own contact details and banner, and swaps MariaDB 11's collation
# for one MySQL 8 and older MariaDB accept.
import re, sys

PEOPLE = ('accounts', 'account_billing', 'account_shipping', 'login_sessions', 'orders', 'order_items', 'password_resets')

s = open(sys.argv[1], encoding='utf-8').read()
for table in PEOPLE:
    s = re.sub(r"--\n-- Dumping data for table `%s`\n--\n\nINSERT INTO `%s`.*?;\n\n" % (table, table), "", s, flags=re.S)

# store_info row 1: the admin panel edits it, so it has to exist, but with nothing of ours in it
def store_info(m):
    cols = [c.strip(' `') for c in m.group(1).split(',')]
    vals = re.findall(r"'(?:[^'\\]|\\.)*'|NULL|[-\d.]+", m.group(2))
    keep = {'id': '1', 'store_name': "'iB Store'", 'company_name': "'My Company'", 'email': "'store@example.com'",
            'website': "'https://example.com/'", 'currency': "'CAD'", 'tax_rate': '0.00', 'shipping_flat': '0.00',
            'timezone': "'UTC'", 'created_at': vals[cols.index('created_at')], 'updated_at': vals[cols.index('updated_at')]}
    return "INSERT INTO `store_info` (%s) VALUES\n(%s);" % (m.group(1), ', '.join(keep.get(c, 'NULL') for c in cols))
s = re.sub(r"INSERT INTO `store_info` \((.*?)\) VALUES\n\((.*?)\);", store_info, s, count=1, flags=re.S)

s = s.replace('utf8mb4_uca1400_ai_ci', 'utf8mb4_unicode_ci')
sys.stdout.write(s)
