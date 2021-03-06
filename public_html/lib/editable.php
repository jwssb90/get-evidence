<?php

  // Copyright 2009 Scalable Computing Experts
  // Author: Tom Clegg

require_once (dirname(dirname(dirname(__FILE__)))."/textile-2.0.0/classTextile.php");
require_once ("lib/oddsratio.php");

$gTheTextile = new Textile;
$gDisableEditing = FALSE;

function editable($id, $content, $title="", $options=false)
{
  global $gDisableEditing;
  global $gTheTextile;

  if (ereg ('__oddsratio$', $id))
    return editable_oddsratio ($id, $content, $title, $options);
  else if (ereg ('__f_variant_quality', $id))
    return editable_quality ($id, $content, $title, $options);
  else {
    /*
    if ($options &&
	is_array ($options["select_options"]) &&
	array_key_exists ($content, $options["select_options"]))
      $html = "<P>".$options["select_options"][$content]."</P>";
    else
    */
    $previewtextile =& $content;
    if (is_array ($options) &&
	array_key_exists ("previewtextile", $options))
      $previewtextile =& $options["previewtextile"];

    $html = $gTheTextile->textileRestricted ($previewtextile);

    if (trim($html) == "") $html = "<P>&nbsp;</P>";

    if (strlen($title) < 60 && !preg_match ('{<(b|strong)\b}i', $title))
      $title = "<strong>$title</strong>";
  }

  $html = "<DIV id=\"toolbar_$id\" class=\"toolbar\">"
    . "<P class=\"toolbar_title\">$title</P></DIV>"
    . "<SPAN id=\"preview_$id\">"
    . $html
    . "</SPAN>";

  if ($gDisableEditing)
    return $html;
  if (!getCurrentUser())
    return "<SPAN id=\"$id\" class=\"uneditable\">$html</SPAN>";

  $selector = "";
  if ($options && is_array($options["select_options"])) {
    $selector = "<P style=\"display:none;\"><SELECT id=\"edited_$id\" name=\"edited_$id\" onchange=\"editable_check_unsaved(this); editable_save();\">\n";
    foreach ($options["select_options"] as $k => $v) {
      $selected = ($content == $k) ? " selected" : "";
      $selector .= "<OPTION value=\"".htmlentities($k)."\"$selected>".htmlspecialchars($v)."</OPTION>\n";
    }
    $selector .= "</SELECT></P>";
  }
  return ("<SPAN id=\"$id\" class=\"editable\">" .
	  (strlen($options["tip"]) ? "<P class=\"csshide\" id=\"tip_$id\">$options[tip]</P>" : "") .
	  $html .
	  $selector .
	  "<INPUT type=\"hidden\" id=\"orig_$id\" value=\"".htmlentities($content)."\"/>" .
	  "</SPAN>");
}

function editable_oddsratio ($id, $content, $title, $options)
{
  global $gDisableEditing;
  $editable = !$gDisableEditing && getCurrentUser();

  $html = "";
  if ($content == "")
    $figs = array();
  else
    $figs = json_decode ($content, true);
  $trclass = ($options["rownumber"] % 4 < 2) ? " class=\"altcolor\"" : "";
  $html .= "<TR$trclass>";
  $html .= "<TD class=\"rowlabel\">$title</TD>";
  $empty = 1;
  foreach (array ("case_pos", "case_neg", "control_pos", "control_neg") as $x) {
    $cellid = "{$id}__o_{$x}__";
    if (!isset ($figs[$x]) || !strlen ($figs[$x])) {
      if (!$editable)
	$figs[$x] = "-";
    }
    else {
      $empty = 0;
      $figs[$x] = $figs[$x] + 0;
    }

    $cell = $figs[$x];
    if ($editable) {
      $html .= "<TD id=\"$cellid\" class=\"editable clicktoedit\"><SPAN id=\"preview_$cellid\">{$cell}</SPAN><INPUT type=\"hidden\" id=\"orig_$cellid\" name=\"orig_$cellid\" value=\"".htmlentities($figs[$x])."\"/></TD>\n";
    }
    else {
      $html .= "<TD>{$cell}</TD>\n";
    }
  }
  $OR = oddsratio_compute($figs, true);
  if ($OR != "-") $OR = "<STRONG>$OR</STRONG>";
  $html .= "<TD>$OR</TD>\n";
  $html .= "</TR>\n";
  if ($empty && !$editable) return "";
  return $html;
}

$gQualityAxes = array ("Computational" => "One point for each consistent prediction, substract points if there are conflicting results:
<UL class=\"tipped\">
<LI>Other variants in this gene cause similar disease</LI>
<LI>NBLOSUM100 score &gt;= 3</LI>
<LI>Nonsense mutation (NBLOSUM100 = 10)</LI>
<LI>evolutionary conservation (minimum of three species)</LI>
<LI>presence in active domain</LI>
<LI>SIFT</LI>
<LI>PolyPhen</LI>
<LI>GVGD</LI>
<LI>etc.</LI>
</UL>",
		       "Functional" => "One point for each experiment supporting the result, and penalize one point for conflicting results from:
<UL class=\"tipped\">
<LI>enzyme extracts</LI>
<LI>cell lines</LI>
<LI>animal models</LI>
<LI>etc.</LI>
</UL>",
		       "Case/Control" => "A combination of odds ratio and significance test (currently using Fisher&rsquo;s Exact Test). For protective alleles, use inverse&nbsp;OR&nbsp;=&nbsp;1&divide;OR. Do not count related individuals, count probands -- i.e., one per family.
<UL class=\"tipped\">
<LI>0 points if no higher ranking is allowed</LI>
<LI>1 point if OR &gt; 1 and significance &lt;= 0.1</LI>
<LI>2 points if OR &gt;= 1.5 and significance &lt;= 0.05</LI>
<LI>3 points if OR &gt;= 2 and significance &lt;= 0.025</LI>
<LI>4 points if OR &gt;= 3 and significance &lt;= 0.01</LI>
<LI>5 points if OR &gt;= 5 and significance &lt;= 0.0001</LI>
</UL>",
		       "Familial" => "<UL class=\"tipped\">
<LI>0 points for no familial information</LI>
<LI>1 point for LOD &gt;= 0.5</LI>
<LI>2 points for LOD &gt;= 1</LI>
<LI>3 points for LOD &gt;= 1.5, at least two families</LI>
<LI>4 points for LOD &gt; 3, at least two families</LI>
<LI>5 points for LOD &gt; 5, at least two families</LI>
</UL>",
		       "Disease Severity" => "Downgraded according to disease penetrance (e.g., Crohn&rsquo;s disease would be moderate or severe, but \"increased susceptibility\" could only mean that the chances are increased by ~.15% and so would be called mild).
<UL class=\"tipped\">
<LI>0 points for benign</LI>
<LI>1 point for very low expectation of having symptoms for this genotype, very low penetrance (e.g., susceptibility to Crohn&rsquo;s with a 4-fold relative risk, causing an overall risk of ~.7%)</LI>
<LI>2 points for mild effect on quality of life or unlikely to be symptomatic (Cystinuria)</LI>
<LI>3 points for moderate effect on quality of life (e.g., Familial Mediterranean Fever)</LI>
<LI>4 points for severe effect: causes serious disability or reduces life expectancy (e.g., Sickle-cell, Stargardt&rsquo;s disease)</LI>
<LI>5 points for very severe effect, lethal by early adulthood (e.g., Lethal junctional epidermolysis bullosa, Adrenoleukodystrophy)</LI>
</UL>",
		       "Treatability" => "<UL class=\"tipped\">
<LI>0 points for no clinical evidence supporting intervention (e.g., PAF acetylhydrolase deficiency)</LI>
<LI>1 point for incurable: treatment only to alleviate symptoms</LI>
<LI>2 points for potentially treatable: Treatment is in development or controversial</LI>
<LI>3 points for treatable but a significant fraction do not require treatment (Cystinuria)</LI>
<LI>4 points for treatable: Standard treatment reduces the amount of mortality/morbidity but does not eliminate it (e.g., sickle-cell disease)</LI>
<LI>5 points for extremely treatable: Well-established treatment essentially eliminates the effect of the disease (e.g., PKU)</LI>
</UL>");

function editable_quality ($id, $content, $title, $options)
{
    global $gDisableEditing;
    $editable = !$gDisableEditing && getCurrentUser();

    $rationale = json_decode ($content["variant_quality_text"], true);

    $html = "<TABLE class=\"quality_table\">\n";
    $header_row = "<TR><TH class=\"rowlabel\" width=\"120\">Variant evidence</TH><TH width=\"1\"></TH><TH width=\"16\"></TH><TH width=\"1\"></TH><TH width=\"*\"></TH></TR>\n";
    $empty_row = "<TR><TD colspan=\"5\">&nbsp;</TD></TR>\n";
    $html .= $header_row;

    $axis_index = 0;
    global $gQualityAxes;
    foreach ($gQualityAxes as $axis => $desc) {
	if ($axis_index == 4) {
	    $html .= $empty_row;
	    $html .= ereg_replace ("Variant evidence", "Clinical&nbsp;importance", $header_row);
	}

	if (strlen($content["variant_quality"]) <= $axis_index)
	    $score = "-";
	else {
	    $score = substr($content["variant_quality"], $axis_index, 1);
	    if ($score === "!") $score = "-1";
	    else if (!ereg ('^[0-5]$', $score))
		$score = "-";
	}

	$html .= "<TR>\n";
	$html .= "<TD class=\"rowlabel nowrap\"><SPAN onmouseover=\"Tip('".htmlspecialchars(ereg_replace("\n","\\n",addslashes($desc)),ENT_QUOTES)."',BALLOON,true,FIX,[this,0,0],FOLLOWMOUSE,false,ABOVE,true,WIDTH,-400);\" onmouseout=\"UnTip();\">$axis</SPAN></TD>\n";

	$cellid = "{$id}__o_{$axis_index}__";

	$cancel_cell = "<TD></TD>";
	$stars = "";
	for ($i=($editable ? "-" : -1);
	     $i === "-" || $i <= 5;
	     $i = ($i === "-" ? -1 : $i+1)) {
	    if (!$editable && $i==0)
		continue;
	    $attrs = "width=\"16\" height=\"16\" alt=\"\"";
	    if ($editable) {
		$arg_i = $i === "-" ? "'-'" : $i;
		$attrs .= " onclick=\"editable_5star_click('{$cellid}',$arg_i);\"";
	    }
	    if ($i != 0 && $i != "-") {
		$id_i = $i < 0 ? "N".(-$i) : $i;
		$attrs .= " id=\"star{$id_i}_{$cellid}\"";
	    }

	    if ($i === "-")
		$stars .= "<SPAN $attrs class=\"halfthere x\">&times;</SPAN>";
	    else {
		if ($i == 0 || $score == "-")
		    $attrs .= " class=\"halfthere\"";

		if ($i > 0 && $i <= $score)
		    $stars .= "<IMG src=\"/img/star-blue16.png\" $attrs />";
		else if ($i < 0 && $score < 0 && $i <= $score)
		    $stars .= "<IMG src=\"/img/star-red16.png\" $attrs />";
		else
		    $stars .= "<IMG src=\"/img/star-white16.png\" $attrs />";
	    }

	    if ($i === "-") {
		// Show the "X" (null) button last, although I made it first
		$cancel_cell = $stars;
		$stars = "";
	    }
	}
	$html .= "<TD class=\"nowrap\">$stars</TD>\n";

	if ($editable) {
	    $html .= "<TD id=\"$cellid\" class=\"editable 5star\"><SPAN id=\"preview_$cellid\">{$score}</SPAN><INPUT type=\"hidden\" id=\"orig_$cellid\" name=\"orig_$cellid\" value=\"".htmlentities($score)."\"/></TD><TD>{$cancel_cell}</TD>\n";
	}
	else if ($score)
	    $html .= "<TD>$score</TD><TD></TD>\n";
	else
	    $html .= "<TD></TD><TD></TD>\n";

	$preview = editable_quality_preview ($rationale[$axis_index]);
	$html .= "<TD class=\"left text\" rowspan=\"2\"><SPAN id=\"rationale_$cellid\">{$preview}</SPAN></TD>";

	$html .= "</TR>\n";
	$html .= "<TR><TD colspan=\"3\"></TD></TR>\n";
	++$axis_index;
    }

    $html .= $empty_row;
    $html .= "</TABLE>\n";
    $html .= "<INPUT type=\"hidden\" id=\"orig_{$id}_text__\" value=\"";
    $html .= htmlentities ($content[variant_quality_text]);
    $html .= "\" />";

    return $html;
}

function editable_quality_preview (&$rationale)
{
    global $gTheTextile;
    $html = '';
    $html .= $gTheTextile->textileRestricted ($rationale["text"]);
    if ($rationale["seealso"] && sizeof($rationale["seealso"])) {
	$html .= "<P>See ";
	foreach ($rationale["seealso"] as $a) {
	    if ($a == "0") {
		$html .= "unpublished research (below)";
		continue;
	    }
	    $summary = preg_replace ('/<.*?>/', '', article_get_summary ($a));
	    if (preg_match ('/^(.*?)(\.|,.*?\.).*?\..*?\.\s*(\d\d\d\d)[;\s]/s',
			    $summary, $regs)) {
		$summary = $regs[1]
		    . ($regs[2] == '.' ? '' : ' et al.')
		    . ' ' . $regs[3]
		    . ' (' . $a . ')';
	    }
	    else
		$summary = $a;
	    $html .= "<SPAN onmouseover=\"evidence_article_citation_tip(this,'{$a}');\" onmouseout=\"UnTip();\">{$summary}</SPAN>";
	    $html .= ", ";
	}
	$html = ereg_replace (', $', '', $html);
	$html .= ".</P>\n";
    }
    return $html;
}

?>
