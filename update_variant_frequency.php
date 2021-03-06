#!/usr/bin/php
<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

chdir ('public_html');
require_once 'lib/setup.php';


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";


print "Parsing hapmap data in taf table...";
$q=theDb()->query ("CREATE TEMPORARY TABLE hapmap_tmp
 AS SELECT chr, chr_pos, allele,
 CONVERT(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING(taf,LOCATE('\"all_n\": ',taf)+9),',',1),'}',1),UNSIGNED) num,
 CONVERT(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING(taf,LOCATE('\"all_d\": ',taf)+9),',',1),'}',1),UNSIGNED) denom
 FROM taf
 WHERE taf LIKE '%\"all_n\": %'
 ");
if (theDb()->isError($q)) die ($q->getMessage());
print theDb()->affectedRows();
print "\n";


print "Copying to allele_frequency...";
theDb()->query ("REPLACE INTO allele_frequency
 (chr,chr_pos,allele,dbtag,num,denom)
 SELECT chr,chr_pos,allele,?,num,denom
 FROM hapmap_tmp",
		array ('HapMap'));
print theDb()->affectedRows();
print "\n";


theDb()->query ("DROP TEMPORARY TABLE hapmap_tmp");


print "Merging frequencies from multiple databases...";
theDb()->query ("CREATE TEMPORARY TABLE allele_frequency_merge AS SELECT chr,chr_pos,allele,num,denom FROM allele_frequency LIMIT 0");
theDb()->query ("ALTER TABLE allele_frequency_merge ADD UNIQUE(chr,chr_pos,allele)");
//theDb()->query ("ALTER TABLE allele_frequency_merge ADD variant_id BIGINT UNSIGNED");
//theDb()->query ("ALTER TABLE allele_frequency_merge ADD INDEX(variant_id)");
theDb()->query ("INSERT IGNORE INTO allele_frequency_merge
 (chr,chr_pos,allele,num,denom)
 SELECT
 chr,chr_pos,allele,SUM(num),SUM(denom)
 FROM allele_frequency
 GROUP BY chr,chr_pos,allele");
print theDb()->affectedRows();
print "\n";


print "Creating variant_chr_pos from variant_locations...";
theDb()->query ("CREATE TEMPORARY TABLE variant_chr_pos AS SELECT DISTINCT variant_id,chr,chr_pos,allele FROM variant_locations WHERE variant_id IS NOT NULL");
print theDb()->affectedRows();
print "\n";


print "Adding index...";
theDb()->query ("ALTER TABLE variant_chr_pos ADD UNIQUE (variant_id,chr,chr_pos,allele)");
print theDb()->affectedRows();
print "\n";


print "Adding variants from variant_occurs...";
theDb()->query ("INSERT IGNORE INTO variant_chr_pos SELECT variant_id,chr,chr_pos,allele FROM variant_occurs");
print theDb()->affectedRows();
print "\n";


print "Updating variant_frequency...";
theDb()->query ("LOCK TABLES variant_frequency WRITE");
theDb()->query ("DELETE FROM variant_frequency WHERE 1=1");
$q=theDb()->query ("INSERT INTO variant_frequency
 (variant_id, num, denom, f)
 SELECT vcp.variant_id, num, denom, num/denom
 FROM variant_chr_pos vcp
 LEFT JOIN allele_frequency_merge afm
  ON vcp.chr=afm.chr AND vcp.chr_pos=afm.chr_pos AND vcp.allele=afm.allele
 WHERE afm.chr IS NOT NULL
 ON DUPLICATE KEY UPDATE variant_frequency.num=variant_frequency.num+afm.num, variant_frequency.denom=variant_frequency.denom+afm.denom, f=variant_frequency.num/variant_frequency.denom");
if (theDb()->isError($q)) die ($q->getMessage());
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");

?>
