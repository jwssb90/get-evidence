daily: update_genomes dump_database vis_data_local
install: php-openid-2.1.3 textile-2.0.0 public_html/js/wz_tooltip.js public_html/js/tip_balloon.js

php-openid-2.1.3:
	wget -c http://openidenabled.com/files/php-openid/packages/php-openid-2.1.3.tar.bz2
	[ `md5sum php-openid-2.1.3.tar.bz2 | head -c 32` = de51927c576f06d54e4a89665bc32391 ]
	tar xjf php-openid-2.1.3.tar.bz2

textile-2.0.0:
	wget -c http://textile.thresholdstate.com/file_download/2/textile-2.0.0.tar.gz
	[ `md5sum textile-2.0.0.tar.gz | head -c 32` = c4f2454b16227236e01fc1c761366fe3 ]
	tar xzf textile-2.0.0.tar.gz
	patch -p0 <textile-2.0.0-php-5.2.4.patch

public_html/js/wz_tooltip.js:
	wget -c http://www.walterzorn.com/scripts/wz_tooltip.zip
	[ `md5sum wz_tooltip.zip | head -c 32` = 6b78dce5ab64ed21d278646f541fbc7a ]
	mkdir -p wz_tooltip
	(cd wz_tooltip && unzip ../wz_tooltip.zip)
	cp -p wz_tooltip/wz_tooltip.js public_html/js/

public_html/js/tip_balloon.js:
	wget -c http://www.walterzorn.com/scripts/tip_balloon.zip
	mkdir -p wz_tooltip
	(cd wz_tooltip && unzip ../tip_balloon.zip)
	mkdir -p public_html/js/tip_balloon
	cp -p wz_tooltip/tip_balloon/* public_html/js/tip_balloon/
	perl -p -e 's:".*?":"/js/tip_balloon/": if m:^config\.\s*BalloonImgPath:' < wz_tooltip/tip_balloon.js > public_html/js/tip_balloon.js

GETEVIDENCEHOST?=evidence.personalgenomes.org
TRAITOMATICHOST?=snp.oxf.freelogy.org
CACHEDIR=$(shell pwd)/tmp
CACHEFILE=$(CACHEDIR)/allsnps-$(TRAITOMATICHOST).txt
ALLSNPSURL?=http://$(TRAITOMATICHOST)/browse/allsnps/public
PID:=$(shell echo $$PPID)
update_genomes: remove_cachefile import_genomes
remove_cachefile:
	rm -f $(CACHEFILE) $(CACHEFILE).tmp.*
$(CACHEFILE):
	mkdir -p $(CACHEDIR)
	rm -f $(CACHEFILE).tmp.*
	wget -O$(CACHEFILE).tmp.$(PID) $(ALLSNPSURL)
	mv $(CACHEFILE).tmp.$(PID) $(CACHEFILE)
import_genomes: $(CACHEFILE)
	./import_genomes.php $(CACHEFILE)

dump_database:
	./dump_database.php public_html/get-evidence.sql.gz

vis_data_local: latest_flat_tmp_local latest_flat latest_flat.gz vis_data
vis_data_http: latest_flat_tmp_http latest_flat latest_flat.gz vis_data
latest_flat_tmp_local:
	mkdir -p $(CACHEDIR)
	(cd public_html && php ./download.php latest flat) > $(CACHEDIR)/latest-flat.tsv.tmp
latest_flat_tmp_http:
	mkdir -p $(CACHEDIR)
	wget -O$(CACHEDIR)/latest-flat.tsv.tmp http://$(GETEVIDENCEHOST)/latest-flat.tsv
latest_flat:
	mv $(CACHEDIR)/latest-flat.tsv.tmp public_html/latest-flat.tsv
latest_flat.gz: latest_flat
	gzip -9n <public_html/latest-flat.tsv >public_html/latest-flat.tsv.gz
vis_data:
	cd get_evidence_vis && ./ProcessTableForVis.pl ../public_html/latest-flat.tsv nsSNP-freq.gff >../public_html/latest_vis_data.tsv.tmp
	cd public_html && mv latest_vis_data.tsv.tmp latest_vis_data.tsv
