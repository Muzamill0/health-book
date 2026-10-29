-- Removes the money-back guarantee wording (no refunds are offered).
-- Only touches text that mentions the guarantee; safe to run more than once.
-- In phpMyAdmin: select your database → Import → this file.

-- Announcement bar: drop the guarantee line
UPDATE `settings`
SET `setting_value` = TRIM(BOTH '\n' FROM REPLACE(REPLACE(REPLACE(`setting_value`, '\r\n', '\n'), '30-day money-back guarantee', ''), '\n\n', '\n'))
WHERE `setting_key` = 'announcement_text' AND `setting_value` LIKE '%money-back guarantee%';

-- Text under the buy button
UPDATE `settings`
SET `setting_value` = REPLACE(`setting_value`, '30-day guarantee', 'Lifetime access')
WHERE `setting_key` = 'guarantee_text' AND `setting_value` LIKE '%30-day guarantee%';

-- FAQ
UPDATE `items`
SET `title` = 'Can I get a refund?',
    `body`  = 'Because the cookbooks are digital files delivered instantly after payment, all sales are final and we do not offer refunds. Please review the books included before you buy.'
WHERE `type` = 'faq' AND `body` LIKE '%money-back guarantee%';

-- Refund Policy page
UPDATE `pages`
SET `content` = '<p>All sales are final. Because our cookbooks are digital products delivered instantly after payment, we do not offer refunds, returns or exchanges once an order is completed.</p><p>Please review the product details and the books included before purchasing. If you have trouble downloading your files, contact us and we will help you get access.</p>'
WHERE `slug` = 'refund-policy' AND `content` LIKE '%money-back guarantee%';
