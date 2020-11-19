/*==============================================================================================
// Created by : Daniel
// File description : Lead, Prospect, Customer system location migration script
// Special - notes : none
// Tables used : tbl_customer_address, tbl_customer_address2, tbl_customer_address3, tbl_customer_contacts, tbl_customer_contacts2, tbl_customer_contacts_customer_address_map, tbl_customer_contacts_customer_address_map2, tbl_customer_contacts_customer_address_map3, tbl_customer_history, tbl_customer_history2
// Stored procedures : none
// Triggers used : none
// ----------------------------------------------------------------------------------------------*/

USE myfc_master3;

/*ALTER TABLE tbl_customers ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE tbl_customer_address ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE tbl_customer_contacts ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE tbl_customer_contacts_customer_address_map ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE quote ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE quote_service_address ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE quote_service_address_line_item ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE quote_service_address_line_item_upsell ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE customer_history ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE tbl_customer_history ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE customer_document ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE tbl_routes ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE tbl_trucks ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
ALTER TABLE tbl_tasks ADD COLUMN old_id INT(11) NULL DEFAULT NULL;
*/

UPDATE quote_service_address SET old_id = NULL;
UPDATE quote_service_address_line_item SET old_id = NULL;
UPDATE tbl_customers SET old_id = NULL;
UPDATE tbl_customer_address SET old_id = NULL;
UPDATE tbl_trucks SET old_id = NULL;
UPDATE tbl_routes SET old_id = NULL;
UPDATE tbl_tasks SET old_id = NULL;
UPDATE quote SET old_id = NULL;
UPDATE tbl_customer_contacts SET old_id = NULL;

CREATE TABLE tbl_customers2 LIKE tbl_customers;
CREATE TABLE tbl_customer_address2 LIKE tbl_customer_address;
CREATE TABLE tbl_customer_address3 LIKE tbl_customer_address2;
CREATE TABLE tbl_customer_contacts2 LIKE tbl_customer_contacts;
CREATE TABLE tbl_customer_contacts_customer_address_map2 LIKE tbl_customer_contacts_customer_address_map;
CREATE TABLE tbl_customer_contacts_customer_address_map3 LIKE tbl_customer_contacts_customer_address_map;
CREATE TABLE schedule2 LIKE schedule;
CREATE TABLE quote2 LIKE quote;
CREATE TABLE quote3 LIKE quote;
CREATE TABLE quote_service_address2 LIKE quote_service_address;
CREATE TABLE quote_service_address3 LIKE quote_service_address;
CREATE TABLE quote_service_address_line_item2 LIKE quote_service_address_line_item;
CREATE TABLE quote_service_address_line_item_upsell2 LIKE quote_service_address_line_item_upsell;
CREATE TABLE customer_history2 LIKE customer_history;
CREATE TABLE customer_history3 LIKE customer_history;
CREATE TABLE tbl_customer_history2 LIKE tbl_customer_history;
CREATE TABLE customer_document2 LIKE customer_document;

#SET @old_location = '64';
#SET @new_location = '117';

#SET @old_location = '14';
#SET @new_location = '118';

#SET @old_location = '57';
#SET @new_location = '113';

SET @old_location = '101';
SET @new_location = '120';

## New Customers ##

#SELECT MAX(in_customer_id) + 1 INTO @counter FROM tbl_customers;
#SET @sql = CONCAT('ALTER TABLE tbl_customers2 AUTO_INCREMENT=',@counter);
#PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

#INSERT INTO tbl_customers2 (SELECT '', in_customer_id__parent, in_franchise_id , st_company_name , parent_company_id, CONCAT(st_company_name, '-', in_customer_id) as qb_company_name, st_name, st_first_name, st_last_name, st_email, `password`, st_phone, extension, st_mobile_number, st_fax, st_customer_street1, st_customer_street2, st_customer_city, st_customer_zip, st_customer_state, st_website, st_account_no, st_account_manager, dt_acquired, flg_is_active, in_added_by, flg_is_lead, in_source_id , flg_send_via_email, flg_send_via_fax, flg_send_via_snail_email, flg_washlog_type, in_invoice_type, flg_inv_generated, st_latest_history, en_viability, flg_quote_created, flg_is_sign, flg_fuel_surcharge, flg_national_account, flg_follow_ups, in_location_type, @new_location , assign_to , lead_id, assign_date, `level`, display_date, show_today, dt_created, dt_modified, flg_is_quickbook_sync, st_quickbook_customer_id, in_main_contact_id, st_tags, pro_cus_date, term, latLng, latlng_found, blanket_po, additional_info, invoice_instruction, unit_per_invoice, in_customer_id, flg_require_water_reclaim, parent_customer, flg_lock_contact_edits, email_generated FROM tbl_customers c HAVING (SELECT GROUP_CONCAT(DISTINCT in_location_id SEPARATOR ', ') FROM tbl_customer_address ca2 WHERE c.in_customer_id = ca2.in_customer_id) = @old_location AND st_tags NOT LIKE '%prospect%');

#INSERT INTO tbl_customers (SELECT * FROM tbl_customers2);
/*
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM schedule WHERE service_address_id IN (SELECT in_customer_address_id FROM tbl_customer_address WHERE in_location_id = @new_location);
SET FOREIGN_KEY_CHECKS=1;
DELETE FROM tbl_customer_contacts_customer_address_map WHERE in_customer_address_id IN (SELECT in_customer_address_id FROM tbl_customer_address WHERE in_location_id = @new_location);
DELETE FROM quote WHERE id IN (SELECT quote_id FROM quote_service_address WHERE service_address_id IN (SELECT in_customer_address_id FROM tbl_customer_address WHERE in_location_id = @new_location));
DELETE FROM quote_service_address_line_item_upsell WHERE quote_service_address_line_item_id IN (SELECT id FROM quote_service_address_line_item WHERE quote_service_address_id IN (SELECT id FROM quote_service_address WHERE service_address_id IN (SELECT in_customer_address_id FROM tbl_customer_address WHERE in_location_id = @new_location)));
DELETE FROM quote_service_address_line_item WHERE quote_service_address_id IN (SELECT id FROM quote_service_address WHERE service_address_id IN (SELECT in_customer_address_id FROM tbl_customer_address WHERE in_location_id = @new_location));
DELETE FROM quote_service_address WHERE service_address_id IN (SELECT in_customer_address_id FROM tbl_customer_address WHERE in_location_id = @new_location);
UPDATE tbl_customers SET flg_is_active = 1 WHERE in_location_id = @new_location;
DELETE FROM tbl_customer_address WHERE in_location_id = @new_location;
DELETE FROM tbl_customer_contacts WHERE in_location_id = @new_location;
DELETE FROM customer_history WHERE customer_id IN (SELECT in_customer_id FROM tbl_customers WHERE in_location_id = @new_location);
DELETE FROM tbl_customer_history WHERE in_customer_id IN (SELECT in_customer_id FROM tbl_customers WHERE in_location_id = @new_location);
DELETE FROM customer_document WHERE customer_id IN (SELECT in_customer_id FROM tbl_customers WHERE in_location_id = @new_location);
DELETE FROM tbl_trucks WHERE in_location_id = @new_location;
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM tbl_routes WHERE in_location_id = @new_location;
SET FOREIGN_KEY_CHECKS=1;
DELETE FROM tbl_tasks WHERE BINARY st_location = BINARY @new_location;
*/

## New Customer Addresses ##

SELECT MAX(in_customer_address_id) + 1 INTO @counter FROM tbl_customer_address;
SET @sql = CONCAT('ALTER TABLE tbl_customer_address2 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO tbl_customer_address2 (SELECT '', cust_additional_info, in_customer_id, @new_location, st_service_rec_name, st_service_address_name, st_service_street1, st_service_street2, st_service_city, st_service_zip, st_service_state, location_invoice_info, flg_is_active, flg_fuel_surcharge, dt_created, dt_modified, send_invoice_method, local_tax_applicable, tax_name, tax_rate, auto_approve_invoice, term, latLng, latlng_found, blanket_po, in_customer_address_id, st_phone, extension FROM tbl_customer_address WHERE in_location_id = @old_location AND in_customer_id NOT IN (SELECT in_customer_id FROM tbl_customers WHERE st_tags LIKE '%prospect%'));

#UPDATE tbl_customer_address2 ca SET in_customer_id = (SELECT in_customer_id FROM tbl_customers c WHERE c.old_id = ca.in_customer_id);

INSERT INTO tbl_customer_address (SELECT * FROM tbl_customer_address2 WHERE in_customer_id != '0');


## Addresses for Existing Customers with different locations ##


SELECT MAX(in_customer_address_id) + 1 INTO @counter FROM tbl_customer_address2;
SET @sql = CONCAT('ALTER TABLE tbl_customer_address3 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO tbl_customer_address3 (SELECT '', cust_additional_info, in_customer_id, @new_location, st_service_rec_name, st_service_address_name, st_service_street1, st_service_street2, st_service_city, st_service_zip, st_service_state, location_invoice_info, flg_is_active, flg_fuel_surcharge, dt_created, dt_modified, send_invoice_method, local_tax_applicable, tax_name, tax_rate, auto_approve_invoice, term, latLng, latlng_found, blanket_po, in_customer_address_id, st_phone, extension FROM tbl_customer_address ca WHERE in_location_id = @old_location HAVING (SELECT GROUP_CONCAT(DISTINCT in_location_id SEPARATOR ', ') FROM tbl_customer_address ca2 WHERE ca.in_customer_id = ca2.in_customer_id) != @old_location AND in_customer_id NOT IN (SELECT in_customer_id FROM tbl_customers WHERE st_tags LIKE '%prospect%'));

#INSERT INTO tbl_customer_address (SELECT * FROM tbl_customer_address3 WHERE in_customer_id != '0');



#UPDATE tbl_customers SET in_location_id = @new_location WHERE in_location_id = @old_location AND st_tags LIKE '%prospect%';
#UPDATE tbl_customer_address SET in_location_id = @new_location WHERE in_location_id = @old_location AND in_customer_id IN (SELECT in_customer_id FROM tbl_customers WHERE st_tags LIKE '%prospect%');


## Leads ##

/*
UPDATE tbl_unqualified_address SET in_location_id = @new_location WHERE in_location_id = @old_location;
UPDATE tbl_unqualified_contacts SET in_location_id = @new_location WHERE in_location_id = @old_location;
UPDATE tbl_unqualified_leads SET in_location_id = @new_location WHERE in_location_id = @old_location;
*/


## Contacts ##

SELECT MAX(in_customer_contact_id) + 1 INTO @counter FROM tbl_customer_contacts;
SET @sql = CONCAT('ALTER TABLE tbl_customer_contacts2 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO tbl_customer_contacts2 (SELECT '', in_customer_id, @new_location, st_name, st_first_name, st_last_name, st_designation, st_phone_number, extension, st_mobile_number, st_fax, st_email, `password`, st_street1, st_street2, st_city, st_zip, st_state, flg_billing_contact, flg_is_active, dt_created, dt_modified, in_customer_address_id, in_customer_contact_id FROM tbl_customer_contacts WHERE in_customer_id IN (SELECT in_customer_id FROM tbl_customer_address WHERE in_location_id = @new_location ));
UPDATE tbl_customer_contacts2 c SET in_customer_address_id = (SELECT in_customer_address_id FROM tbl_customer_address2 c2 WHERE c2.old_id = c.in_customer_address_id);

#INSERT INTO tbl_customer_contacts (SELECT * FROM tbl_customer_contacts2);

INSERT INTO tbl_customer_contacts_customer_address_map2 (SELECT in_customer_contacts_id, in_customer_address_id, st_role, in_customer_contacts_id AS old_id FROM tbl_customer_contacts_customer_address_map WHERE in_customer_address_id IN (SELECT old_id FROM tbl_customer_address2));

#UPDATE tbl_customer_contacts_customer_address_map2 m SET in_customer_contacts_id = (SELECT old_id FROM tbl_customer_contacts2 c2 WHERE c2.in_customer_contact_id = m.in_customer_contacts_id);
UPDATE tbl_customer_contacts_customer_address_map2 m SET in_customer_address_id = (SELECT in_customer_address_id FROM tbl_customer_address2 ca WHERE ca.old_id = m.in_customer_address_id);

#INSERT INTO tbl_customer_contacts_customer_address_map3 (SELECT in_customer_contacts_id, in_customer_address_id, st_role, in_customer_address_id AS old_id FROM tbl_customer_contacts_customer_address_map WHERE in_customer_address_id IN (SELECT in_customer_address_id FROM tbl_customer_address WHERE in_customer_id IN (SELECT in_customer_id FROM tbl_customers c WHERE in_customer_id IN (SELECT in_customer_id FROM tbl_customer_address WHERE in_location_id = @old_location) HAVING (SELECT GROUP_CONCAT(DISTINCT in_location_id SEPARATOR ', ') FROM tbl_customer_address ca2 WHERE c.in_customer_id = ca2.in_customer_id) != @old_location) AND in_location_id = @old_location));

#UPDATE tbl_customer_contacts_customer_address_map3 m SET in_customer_address_id = (SELECT in_customer_address_id FROM tbl_customer_address3 ca WHERE ca.old_id = m.in_customer_address_id);

INSERT INTO tbl_customer_contacts_customer_address_map (SELECT * FROM tbl_customer_contacts_customer_address_map2);
#INSERT INTO tbl_customer_contacts_customer_address_map (SELECT * FROM tbl_customer_contacts_customer_address_map3);


## Schedules ##


SELECT MAX(id) + 1 INTO @counter FROM schedule;
SET @sql = CONCAT('ALTER TABLE schedule2 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO schedule2 (SELECT '', service_address_id, `name`, route_id, service_interval, week_no, weekdays, start_date, end_date, start_time, end_time, is_skipped, skipped_schedule_id, notes, created_date, modified_date FROM schedule WHERE service_address_id IN (SELECT old_id FROM tbl_customer_address2 WHERE in_location_id = @new_location));

UPDATE schedule2 s SET service_address_id = (SELECT in_customer_address_id FROM tbl_customer_address2 ca WHERE ca.old_id = s.service_address_id AND ca.in_customer_id != '0');

INSERT INTO schedule (SELECT * FROM schedule2);


## Quotes - New Customers ##

SELECT MAX(id) + 1 INTO @counter FROM quote;
SET @sql = CONCAT('ALTER TABLE quote2 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT MAX(quote_no) INTO @quote_no FROM quote WHERE id IN (SELECT quote_id FROM quote_service_address WHERE service_address_id IN (SELECT old_id FROM tbl_customer_address2 WHERE in_customer_id != '0'));

INSERT INTO quote2 (SELECT '', @quote_no:=@quote_no+1 as quote_no, franchise_id, customer_id, notes, additional_info, quote_send_type, status, added_by_user_id, note_1, note_2, rejected_note, pdf_name, agreement_pdf_name, minimum_charge, minimum_units, discount, is_sending_email_reminder, serviceables, services, sent, response, is_deleted, created, modified, job_scheduled, save_recurring, end_instance, is_hide, is_approved, id FROM quote WHERE id IN (SELECT quote_id FROM quote_service_address WHERE service_address_id IN (SELECT old_id FROM tbl_customer_address2 WHERE in_customer_id != '0')));
 
#UPDATE quote2 q SET customer_id = (SELECT in_customer_id FROM tbl_customers c WHERE c.old_id = q.customer_id);

INSERT INTO quote (SELECT * FROM quote2);


## Quotes - Existing Customers ##

SELECT MAX(id) + 1 INTO @counter FROM quote;
SET @sql = CONCAT('ALTER TABLE quote3 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT MAX(quote_no) INTO @quote_no FROM quote q WHERE id IN (SELECT quote_id FROM quote_service_address WHERE service_address_id IN (SELECT old_id FROM tbl_customer_address3 WHERE in_customer_id != '0'));

INSERT INTO quote3 (SELECT '', @quote_no:=@quote_no+1 as quote_no, franchise_id, customer_id, notes, additional_info, quote_send_type, status, added_by_user_id, note_1, note_2, rejected_note, pdf_name, agreement_pdf_name, minimum_charge, minimum_units, discount, is_sending_email_reminder, serviceables, services, sent, response, is_deleted, created, modified, job_scheduled, save_recurring, end_instance, is_hide, is_approved, id FROM quote q WHERE id IN (SELECT quote_id FROM quote_service_address WHERE service_address_id IN (SELECT old_id FROM tbl_customer_address3 WHERE in_customer_id != '0')));

INSERT INTO quote (SELECT * FROM quote3);


## Quotes - Update Quote Service Addresses ##

SELECT MAX(id) + 1 INTO @counter FROM quote_service_address;
SET @sql = CONCAT('ALTER TABLE quote_service_address2 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

# New Customers
INSERT INTO quote_service_address2 (SELECT '', quote_id, service_address_id, route_id, added_by_user_id, mileage_upcharge, scheduling_preferences, franchise_id, location_id, is_access_key_required, access_notes, is_signature_required, is_water_reclaimed, notes, is_approved, early_start_time, finish_by_time, job_scheduled, save_recurring, end_instance, minimum_charge, minimum_units, `status`, sent_date, created_date, modified_date, id as old_id FROM quote_service_address WHERE service_address_id IN (SELECT old_id FROM tbl_customer_address2 WHERE in_customer_id != '0'));

UPDATE quote_service_address2 qsa SET service_address_id = (SELECT in_customer_address_id FROM tbl_customer_address2 ca WHERE ca.old_id = qsa.service_address_id AND ca.in_customer_id != '0');
UPDATE quote_service_address2 qsa SET quote_id = (SELECT DISTINCT id FROM quote2 q WHERE q.old_id = qsa.quote_id);

INSERT INTO quote_service_address (SELECT * FROM quote_service_address2);

#Existing Customers
SELECT MAX(id) + 1 INTO @counter FROM quote_service_address;
SET @sql = CONCAT('ALTER TABLE quote_service_address3 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO quote_service_address3 (SELECT '', quote_id, service_address_id, route_id, added_by_user_id, mileage_upcharge, scheduling_preferences, franchise_id, location_id, is_access_key_required, access_notes, is_signature_required, is_water_reclaimed, notes, is_approved, early_start_time, finish_by_time, job_scheduled, save_recurring, end_instance, minimum_charge, minimum_units, `status`, sent_date, created_date, modified_date, id FROM quote_service_address WHERE service_address_id IN (SELECT old_id FROM tbl_customer_address3));

UPDATE quote_service_address3 qsa SET service_address_id = (SELECT in_customer_address_id FROM tbl_customer_address3 ca WHERE ca.old_id = qsa.service_address_id AND ca.in_customer_id != '0');
UPDATE quote_service_address3 qsa SET quote_id = (SELECT DISTINCT id FROM quote2 q WHERE q.old_id = qsa.quote_id);

INSERT INTO quote_service_address (SELECT * FROM quote_service_address3);


## Quote Line Items ##

SELECT MAX(id) + 1 INTO @counter FROM quote_service_address_line_item;
SET @sql = CONCAT('ALTER TABLE quote_service_address_line_item2 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO quote_service_address_line_item2 (SELECT '', quote_service_address_id, service_interval, services_id, unit_types_id, unit_types_display_name, repeat_interval, units, adjusted_price, is_suggested, is_upcharge_exempt, additional_instructions, alternative_for_quote_service_address_line_item, is_frozen, id FROM quote_service_address_line_item WHERE quote_service_address_id IN (SELECT old_id FROM quote_service_address WHERE old_id IS NOT NULL));

UPDATE quote_service_address_line_item2 qsali SET quote_service_address_id = (SELECT id FROM quote_service_address2 qsa WHERE qsa.old_id = qsali.quote_service_address_id AND qsa.service_address_id != 0);

INSERT INTO quote_service_address_line_item (SELECT * FROM quote_service_address_line_item2);


## Quote Line Items ##

SELECT MAX(id) + 1 INTO @counter FROM quote_service_address_line_item_upsell;
SET @sql = CONCAT('ALTER TABLE quote_service_address_line_item_upsell2 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO quote_service_address_line_item_upsell2 (SELECT '', quote_service_address_line_item_id, service_id, upsell_id, adjusted_price, id FROM quote_service_address_line_item_upsell WHERE quote_service_address_line_item_id IN (SELECT old_id FROM quote_service_address_line_item2));

UPDATE quote_service_address_line_item_upsell2 qsaliu SET quote_service_address_line_item_id = (SELECT id FROM quote_service_address_line_item qsali WHERE qsali.old_id = qsaliu.quote_service_address_line_item_id);

INSERT INTO quote_service_address_line_item_upsell (SELECT * FROM quote_service_address_line_item_upsell2);


## Customer History ##

/*SELECT MAX(customer_history_id) + 1 INTO @counter FROM customer_history;
SET @sql = CONCAT('ALTER TABLE customer_history2 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO customer_history2 (SELECT '', customer_id, changed_data, created_date, in_added_by, customer_history_id FROM customer_history WHERE customer_id IN (SELECT old_id FROM tbl_customers WHERE old_id IS NOT NULL) LIMIT 0, 3000);

UPDATE customer_history2 ch SET customer_id = (SELECT in_customer_id FROM tbl_customers c WHERE c.old_id = ch.customer_id);

INSERT INTO customer_history (SELECT * FROM customer_history2);



SELECT MAX(customer_history_id) + 1 INTO @counter FROM customer_history2;
SET @sql = CONCAT('ALTER TABLE customer_history3 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO customer_history3 (SELECT '', customer_id, changed_data, created_date, in_added_by, customer_history_id FROM customer_history WHERE customer_id IN (SELECT old_id FROM tbl_customers WHERE old_id IS NOT NULL) LIMIT 3000, 4000);

UPDATE customer_history3 ch SET customer_id = (SELECT in_customer_id FROM tbl_customers c WHERE c.old_id = ch.customer_id);

INSERT INTO customer_history (SELECT * FROM customer_history3);


SELECT MAX(in_history_id) + 1 INTO @counter FROM tbl_customer_history;
SET @sql = CONCAT('ALTER TABLE tbl_customer_history2 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO tbl_customer_history2 (SELECT '', in_customer_id, st_history_status, st_notes, st_quote_pdf, dt_created, in_added_by, is_duplicate, in_history_id, service_address_id FROM tbl_customer_history WHERE in_customer_id IN (SELECT old_id FROM tbl_customers WHERE old_id IS NOT NULL));

UPDATE tbl_customer_history2 ch SET in_customer_id = (SELECT in_customer_id FROM tbl_customers c WHERE c.old_id = ch.in_customer_id);

INSERT INTO tbl_customer_history (SELECT * FROM tbl_customer_history2);
*/

## Customer Documents ##

/*
SELECT MAX(id) + 1 INTO @counter FROM customer_document;
SET @sql = CONCAT('ALTER TABLE customer_document2 AUTO_INCREMENT=',@counter);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO customer_document2 (SELECT '', customer_id, name, description, type, date_created, in_added_by, id FROM customer_document WHERE customer_id IN (SELECT old_id FROM tbl_customers WHERE old_id IS NOT NULL));

UPDATE customer_document2 cd SET customer_id = (SELECT in_customer_id FROM tbl_customers c WHERE c.old_id = cd.customer_id);

INSERT INTO customer_document (SELECT * FROM customer_document2);
*/


## Trucks ##
INSERT INTO tbl_trucks (SELECT '', st_truck_title, st_truck_name, flg_is_active, flg_is_delete, dt_created, dt_modified, @new_location, st_zipcode, st_address, in_truck_id FROM tbl_trucks WHERE in_location_id = @old_location);


## Routes ##
INSERT INTO tbl_routes (SELECT '', @new_location, st_route_title, in_crew_lead_id, st_crew_member_id, in_truck_id, flg_is_active, flg_is_delete, dt_created, dt_modified, in_route_id FROM tbl_routes WHERE in_location_id = @old_location);

UPDATE tbl_routes r SET in_truck_id = (SELECT in_truck_id FROM tbl_trucks t WHERE t.old_id = r.in_truck_id) WHERE r.old_id IS NOT NULL; 

SET FOREIGN_KEY_CHECKS=0;
UPDATE schedule s SET route_id = (SELECT in_route_id FROM tbl_routes r WHERE r.old_id = s.route_id) WHERE service_address_id IN (SELECT in_customer_address_id FROM tbl_customer_address WHERE old_id IS NOT NULL);
SET FOREIGN_KEY_CHECKS=1;


## Tasks ##
#INSERT INTO tbl_tasks (SELECT '', in_task_type, in_user_id, in_share_user, in_customer_id, st_subject, st_location, dt_due_date, dt_from_time, dt_to_time, flg_day_event, st_description, flg_status, st_color_code, flg_is_delete, in_customer_type, dt_created, dt_modified, old_in_user_id, in_task_id FROM tbl_tasks WHERE in_customer_id IN (SELECT old_id FROM tbl_customers WHERE old_id IS NOT NULL));

#UPDATE tbl_tasks t SET in_customer_id = (SELECT in_customer_id FROM tbl_customers c WHERE c.old_id = t.in_customer_id) WHERE old_id IS NOT NULL;


### Prospects and Leads ###

UPDATE tbl_customer_address ca SET ca.in_location_id = @new_location WHERE ca.in_location_id = @old_location AND ca.in_customer_id IN (SELECT in_customer_id FROM tbl_customers c WHERE c.st_tags LIKE '%prospect%');
UPDATE tbl_customers SET in_location_id = @new_location WHERE in_location_id = @old_location AND st_tags LIKE '%prospect%';
UPDATE tbl_unqualified_leads SET in_location_id = @new_location WHERE in_location_id = @old_location;
UPDATE tbl_unqualified_address ca SET ca.in_location_id = @new_location WHERE ca.in_location_id = @old_location;



DROP TABLE tbl_customers2;
DROP TABLE tbl_customer_address2;
DROP TABLE tbl_customer_address3;
DROP TABLE tbl_customer_contacts2;
DROP TABLE tbl_customer_contacts_customer_address_map2;
DROP TABLE tbl_customer_contacts_customer_address_map3;
DROP TABLE schedule2;
DROP TABLE quote2;
DROP TABLE quote3;
DROP TABLE quote_service_address2;
DROP TABLE quote_service_address3;
DROP TABLE quote_service_address_line_item2;
DROP TABLE quote_service_address_line_item_upsell2;
DROP TABLE customer_history2;
DROP TABLE customer_history3;
DROP TABLE tbl_customer_history2;
DROP TABLE customer_document2;