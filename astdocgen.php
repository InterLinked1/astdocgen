#!/usr/bin/php
<?php
$skipList = array(); # Add app/func names, etc. to this array to skip them in the documentation. End with * to match all names starting with a prefix.
if (file_exists('astdocgen_exclusions.php')) {
	include('astdocgen_exclusions.php');
}
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("exception_error_handler"); # stop the script as soon as anything goes wrong. https://stackoverflow.com/a/10530114/
function xmlObjToArr($obj) { # https://www.php.net/manual/en/book.simplexml.php
	$namespace = $obj->getDocNamespaces(true); 
	$namespace[NULL] = NULL; 

	$children = array(); 
	$attributes = array(); 
	$name = strtolower((string)$obj->getName()); 

	$text = trim((string)$obj); 
	if (strlen($text) <= 0) { 
		$text = NULL; 
	}

	// get info for all namespaces
	if(is_object($obj)) {
		foreach( $namespace as $ns=>$nsUrl ) { 
			// atributes 
			$objAttributes = $obj->attributes($ns, true); 
			foreach( $objAttributes as $attributeName => $attributeValue ) { 
				$attribName = strtolower(trim((string)$attributeName)); 
				$attribVal = trim((string)$attributeValue); 
				if (!empty($ns)) { 
					$attribName = $ns . ':' . $attribName; 
				} 
				$attributes[$attribName] = $attribVal; 
			} 

			// children 
			$objChildren = $obj->children($ns, true); 
			foreach( $objChildren as $childName=>$child ) { 
				$childName = strtolower((string)$childName); 
				if( !empty($ns) ) { 
					$childName = $ns.':'.$childName; 
				}
				$children[$childName][] = xmlObjToArr($child); 
			} 
		} 
	} 

	return array( 
		'name'=>$name, 
		'text'=>$text, 
		'attributes'=>$attributes, 
		'children'=>$children 
	); 
}
function addopt(&$s, &$l, $short, $long, $required, $param) {
	$suffix = "";
	if ($required && $param) {
		$suffix = ":";
	} else if (!$required && $param) {
		$suffix = "::";
	}
	if ($short !== "")
		$s .= $short . $suffix;
	$l[] = $long . $suffix;
}
$shortopts = "";
$longopts = array();
$s = &$shortopts;
$l = &$longopts;
addopt($s, $l, "x", "xml", false, false);
addopt($s, $l, "h", "html", false, false);
addopt($s, $l, "p", "print", false, false);
addopt($s, $l, "f", "file", true, true);
addopt($s, $l, "s", "serialize", false, false);
addopt($s, $l, "", "help", false, false);
$options = getopt($shortopts, $longopts);
$optXML = (isset($options['x']) || isset($options['xml']));
$optHTML = (isset($options['h']) || isset($options['html']));
$optFile = (isset($options['f']) || isset($options['file']));
$optSerialize = (isset($options['s']) || isset($options['serialize']));
$printr = (isset($options['p']) || isset($options['print']));
$optHelp = isset($options['help']);
if ($argc < 2 || $optHelp || (!$optXML && !$optHTML && !$printr) || !$optFile) {
	printf("%s\n", "Usage: astdocgen [OPTION]... [FILE]...");
	printf("%s\n\n", "Generate parseable or parsed documentation from Asterisk XML documentation.");
	printf("  -%s, --%-15s %s\n", "f", "file", "Input file for XML array dump or HTML generation");
	printf("  -%s, --%-15s %s\n", "h", "html", "Generate HTML documentation from XML array dump and write to STDOUT");
	printf("  -%s, --%-15s %s\n", "p", "print", "Generate HTML array dump from XML array dump and write to STDOUT");
	printf("  -%s, --%-15s %s\n", "s", "serialize", "Serialize the generated XML array dump");
	printf("  -%s, --%-15s %s\n", "x", "xml", "Generate array dump of XML from specified file and write to STDOUT");
	printf("\n%s\n", "(C) PhreakNet, 2021");
	exit(2);
}
$filename = (isset($options['f']) ? $options['f'] : $options['file']);
if (!file_exists($filename)) {
	fprintf(STDERR, "Input file does not exist: %s\n", $filename);
	exit(2);
}
if ($optXML) {
	$xmlFile = file_get_contents($filename);
	$xmlFile = str_replace("<literal>", "<![CDATA[<literal>", $xmlFile);
	$xmlFile = str_replace("</literal>", "</literal>]]>", $xmlFile);
	$xmlFile = str_replace("<replaceable>", "<![CDATA[<replaceable>", $xmlFile);
	$xmlFile = str_replace("</replaceable>", "</replaceable>]]>", $xmlFile);
	$xmlFile = str_replace("<filename>", "<![CDATA[<filename>", $xmlFile);
	$xmlFile = str_replace("</filename>", "</filename>]]>", $xmlFile);
	$xmlFile = str_replace("<emphasis>", "<![CDATA[<emphasis>", $xmlFile);
	$xmlFile = str_replace("</emphasis>", "</emphasis>]]>", $xmlFile);
	$xmlFile = preg_replace("/<variable>([A-Z_]+)<\/variable>/", "<![CDATA[<variable>$1</variable>]]>", $xmlFile); # variable tag used for both single words and nodes. We don't want to parse the markdown words, but do want to parse the nodes.
	$xml = simplexml_load_string($xmlFile);
	fwrite(STDERR, "Processing XML - this will take a moment...");
	$array = xmlObjToArr($xml); # this is really slow, but it properly retains attributes on leaf nodes, respects CDATA, etc.
	fwrite(STDERR, PHP_EOL);
	fwrite(STDERR, "Dumping XML...");
	if ($optSerialize)
		echo '<?php $array = ' . var_export($array, true) . ';?>';
	else
		print_r($array);
	fwrite(STDERR, PHP_EOL);
	exit(0);
}
if ($optSerialize) {
	fprintf(STDERR, "Serialize option is incompatible with HTML option" . PHP_EOL);
	exit(2);
}
include($filename);
if ($printr) { # HTML array dump
	if (!isset($array) || !is_array($array)) {
		fprintf(STDERR, "Array not found" . PHP_EOL);
		exit(2);
	}
	fwrite(STDERR, "Dumping XML dump to HTML..." . PHP_EOL);
	echo "<pre>";
	print_r($array);
	echo "</pre>";
	exit(0);
}

if (!isset($array) || !is_array($array)) {
	fprintf(STDERR, "Specified input file does not contain XML dump" . PHP_EOL);
	exit(2);
}
fwrite(STDERR, "Generating HTML..." . PHP_EOL);

echo "<!doctype html><html lang='en'><head><meta charset='utf-8'><title>Asterisk Docs</title>";
echo "<style>
@font-face{
  font-family:'MuseoSans300';
  src:url(/wiki/digium/fonts/MuseoSans_300-webfont.eot);
  src:url(/wiki/digium/fonts/MuseoSans_300-webfont.eot?iefix) format('eot'),
  url(fonts/MuseoSans_300-webfont.woff) format('woff'),
  url(/wiki/digium/fonts/MuseoSans_300-webfont.ttf) format('truetype'),
  url(/wiki/digium/fonts/MuseoSans_300-webfont.svg#webfontwebcqTfV) format('svg');
  font-weight:normal;
  font-style:normal;
}
@font-face{
  font-family:'MuseoSans500';
  src:url(/wiki/digium/fonts/MuseoSans_500-webfont.eot);
  src:url(/wiki/digium/fonts/MuseoSans_500-webfont.eot?iefix) format('eot'),
  url(fonts/MuseoSans_500-webfont.woff) format('woff'),
  url(/wiki/digium/fonts/MuseoSans_500-webfont.ttf) format('truetype'),
  url(/wiki/digium/fonts/MuseoSans_500-webfont.svg#webfontwebcqTfV) format('svg');
  font-style:normal;
}
@font-face{
  font-family:'MuseoSans700';
  src:url(/wiki/digium/fonts/MuseoSans_700-webfont.eot);
  src:url(/wiki/digium/fonts/MuseoSans_700-webfont.eot?iefix) format('eot'),
  url(fonts/MuseoSans_700-webfont.woff) format('woff'),
  url(/wiki/digium/fonts/MuseoSans_700-webfont.ttf) format('truetype'),
  url(/wiki/digium/fonts/MuseoSans_700-webfont.svg#webfontwebcqTfV) format('svg');
  font-weight:normal;
}
emphasis {
	font-weight: bold;
}
replaceable {
	font-style: italic;
}
literal, variable {
	font-family: Consolas, Courier, 'Courier New';
	font-size: 0.95em;
}
.note {
	border: 0.5px solid maroon;
	background-color: #00090fdb;
	padding: 10px;
}
.warning {
	border: 0.5px solid maroon;
	background-color: #00090fdb;
	padding: 10px;
}
.example {
	padding: 15px;
	padding-left: 25px;
	border: 1px dashed gray;
	background-color: #00090f;
	margin: 5px;
}
body {
	background: #f6f6f6;
	font-family: MuseoSans300, sans-serif;
}
body {
	margin: 0;
	padding: 0;
	border: 0;
	overflow: hidden;
	height: 100%;
	max-height: 100%;
}
#doctop {
	display: block;
	height: 45px;
	background-color: #f6772f;
}
#doctop * {
	margin: 0;
	padding: 0;
}
#logo img {
    width: 146px;
    height: 32px;
	padding: 5px;
}
#docmenu, #docbody {
	top: 45px;
}
#docmenu {
	position: absolute;
	width: 325px;
	height: 100%;
	overflow: auto;
	font-size: 0.9em;
	background-color: #2c2c2c;
}
#docmenu ul {
    list-style-type: none;
    padding-left: 10px;
	background-color: #2c2c2c;
	margin-top: 0;
	padding-top: 5px;
	margin-bottom: 0;
}
#docmenu ul, #geninfo {
	color: #b7b7b1;
}
#geninfo {
	text-align: center;
}
table th, table td {
    border: 1px solid white;
    padding: 1px 10px;
}
table {
    border-collapse: collapse;
}
a {
	color: #b7b7b1;
}
a:hover {
	color: #f6772f;
	font-weight: bold;
}
#docbody {
	position: fixed;
	left: 325px;
	right: 0;
	bottom: 0;
	overflow: auto;
	background: #fff;
	padding-left: 15px;
	padding-right: 15px;
	background-color: #2c2c2c;
	color: #b7b7b1;
}
.syntaxbar {
	padding: 15px;
	border: 1px solid gray;
	background-color: #00090f;
}
.panel {
    border-color: #cccccc;
}
h1 {
  font-family: MuseoSans700, sans-serif;
}
h2 {
  font-family: MuseoSans500, sans-serif;
}
h3, h4, h5, h6 {
  font-family: MuseoSans300, sans-serif;
}
header {
	background-color: #cf6225;
}
footer {
	color: #828282;
	background-color: #414141;
}
</style>
";
echo "</head><body>";

function shouldSkip($name) {
	global $skipList;
	if (in_array($name, $skipList)) {
		return true;
	}
	if (PHP_VERSION_ID >= 80000) { # str_starts_with only exists in PHP 8
		$match = false;
		foreach ($skipList as $s) {
			$lastChar = substr($s, -1);
			if ($lastChar === '*') {
				$prefix = substr($s, 0, -1);
				if (str_starts_with($name, $prefix)) {
					return true;
				}
			}
		}
	}
	return false;
}

$docs = $array['children'];
if (!isset($docs['application'])) {
	fprintf(STDERR, "Couldn't find application?\n");
	fprintf(STDERR, "%s\n", print_r(array_keys($docs), true));
	exit(-1);
}
$module = isset($docs['module']) ? $docs['module'] : array();
$apps = $docs['application'];
$funcs = $docs['function'];
$info = $docs['info']; /*! \todo do something with info - these are all tech/channel related things */
$manager = $docs['manager'];
$managerevent = $docs['managerevent']; /*! \todo needs xpointer support */
$configinfo = $docs['configinfo']; /*! \todo needs xpointer support */
$agi = $docs['agi'];

$allDocs = array(
	'Configuration' => $configinfo,
	'Application' => $apps,
	'Function' => $funcs,
	'ManagerAction' => $manager,
	'ManagerEvent' => $managerevent,
	'AgiCommand' => $agi,
);

echo "<div id='doctop'>";
echo "<div id='logo'><a href=''><img src='/fonts/confluence-logo.png'/></a></div>";
echo "</div>";
echo "</div id='docmain'>";
echo "<div id='docmenu'>";
echo "<ul>";
foreach($allDocs as $catName => $cat) {
	$catList = array();
	foreach ($cat as $c) {
		$name = $c['attributes']['name'];
		if (shouldSkip($name)) {
			continue;
		}
		$catList["$name"] = strtolower($catName) . "-" . $name;
	}
	ksort($catList);
	foreach ($catList as $item => $link) {
		echo "<li><a href='#$link'>" . $catName . "_<b>$item</b></a></li>";
	}
}
echo "</ul>";

echo "<div id='geninfo'>";
$version = shell_exec("/sbin/asterisk -V");
echo "<p>$version</p>";
$date = date("Y-m-d H:i");
echo "<p>Generated $date</p>";
echo "</div>";

echo "<br><br><br>"; # otherwise, last few are hidden...
echo "</div>";

echo "<div id='docbody'>";
echo "<table><tr><th>Module</th><th>Support Level</th><th>Deprecated In</th><th>Removed In</th><th>Dependencies</th></tr>";
foreach ($module as $mod) {
	$name = $mod['attributes']['name'];
	if (shouldSkip($name)) {
		continue;
	}
	$dependencies = array();
	$supportLevel = (isset($mod['children']['support_level'][0]['text']) ? $mod['children']['support_level'][0]['text'] : '');
	$deprecated = (isset($mod['children']['deprecated_in'][0]['text']) ? $mod['children']['deprecated_in'][0]['text'] : '');
	$removed = (isset($mod['children']['removed_in'][0]['text']) ? $mod['children']['removed_in'][0]['text'] : '');
	if (isset($mod['children']['depend'])) {
		foreach($mod['children']['depend'] as $d) {
			$dependencies[] = $d['text'];
		}
	}
	if (isset($mod['children']['depend'])) {
		foreach($mod['children']['depend'] as $d) {
			$dependencies[] = $d['text'];
		}
	}
	echo "<tr><td>$name</td><td>$supportLevel</td><td>$deprecated</td><td>$removed</td><td>" . implode(', ', $dependencies) . "</td>";
}
echo "</table>";
foreach($allDocs as $afTypeFull => $appfunc) {
	foreach ($appfunc as $x) {
		$afType = strtolower($afTypeFull);
		$xName = $x['attributes']['name'];
		if (shouldSkip($xName)) {
			fwrite(STDERR, "Skipping $afType $xName..." . PHP_EOL);
			continue;
		}
		fwrite(STDERR, "Processing $afType $xName..." . PHP_EOL);
		$xData = $x['children'];
		echo "<div class='doc-single' id='$afType-$xName'>";
		$hyperlink = "https://wiki.asterisk.org/wiki/display/AST/Asterisk+18+${afTypeFull}_$xName";
		$title = $xName;
		if ($afType === "agicommand")
			$title = strtoupper($title);
		echo "<h2><a href='$hyperlink' target='_blank'>$title" . ($afType === "application" || $afType === "function" ? "()" : "") . "</a></h2>";
		echo "<h3>Synopsis</h3>";
		if (isset($xData['synopsis'])) {
			$synopsis = $xData['synopsis'][0]['text'];
			if ($afType === "configuration") {
				echo "<h3>$synopsis</h3>";
				echo "<p>This configuration documentation is for functionality provided by <code>$xName</code>.</p>";
			} else {
				echo "<p>$synopsis</p>";
			}
		}
		echo "<h3>Description</h3>";
		if (isset($xData['description'][0]['children'])) {
			$description = $xData['description'][0]['children'];
			if (isset($description['para'])) {
				$descPara = $description['para'];
				foreach ($descPara as $para) {
					echo "<p>" . $para['text'] . "</p>";
				}
			}
			if (isset($description['note'])) {
				foreach ($description['note'] as $note) {
					echo "<div class='note'>";
					foreach ($note['children']['para'] as $para) {
						echo "<p>" . $para['text'] . "</p>";
					}
					echo "</div>";
				}
			}
			if (isset($description['warning'])) {
				foreach ($description['warning'] as $warning) {
					echo "<div class='warning'>";
					foreach ($warning['children']['para'] as $para) {
						echo "<p>" . $para['text'] . "</p>";
					}
					echo "</div>";
				}
			}
			if (isset($description['example'])) {
				foreach ($description['example'] as $example) {
					echo "<div class='example'>";
					$title = $example['attributes']['title'];
					$ex = $example['text'];
					echo "<b>Example: $title</b>";
					echo "<pre>";
					$ex = explode("\n", $ex);
					foreach ($ex as $exl) {
						$t = trim($exl);
						if (substr($t, 0, 5) === "exten")
							echo PHP_EOL;
						echo "\t" . $t;
						echo PHP_EOL;
					}
					echo "</pre>";
					echo "</div>";
				}
			}
			if (isset($description['variablelist'])) {
				echo "<ul>";
				foreach ($description['variablelist'][0]['children']['variable'] as $variable) {
					$varName = $variable['attributes']['name'];
					echo "<li><code>$varName</code>";
					if (isSet($variable['children']['para'])) {
						echo " - ";
						foreach ($variable['children']['para'] as $para) {
							echo $para['text'];
						}
					}
					if (isset($variable['children']['value'])) {
						echo "<ul>";
						foreach ($variable['children']['value'] as $varvalue) {
							$vvName = $varvalue['attributes']['name'];
							$vvText = $varvalue['text'];
							echo "<li>$vvName" . ($vvText && strlen($vvText) > 0 ? " - " . $vvText : "") . "</li>";
						}
						echo "</ul>";
					}
					echo "</li>";
				}
				echo "</ul>";
			}
		}
		if (isset($xData['syntax'][0]['children'])) {
			$syntax = $xData['syntax'][0]['children'];
			# AMI stuff requires xpointer, not supported yet.
			if ($afType === "agicommand") {
				echo "<h3>Syntax</h3>";
				echo "<p class='syntaxbar'><code>" . strtoupper($xName);
				if (isset($syntax['parameter'])) {
					foreach ($syntax['parameter'] as $parameter) {
						if (isset($parameter['children']['argument'])) {
							foreach ($parameter['children']['argument'] as $argument) {
								$argName = $argument['attributes']['name'];
								echo strtoupper($argName);
							}
						} else {
							$argName = $parameter['attributes']['name'];
							echo " " . strtoupper($argName);
						}
					}
				}
				echo "</code></p>";
			} else if (isset($xData['syntax'][0]['children']['parameter'])) { # apps/funcs...
				$parameters = $syntax['parameter'];
				echo "<h3>Syntax</h3>";
				echo "<p class='syntaxbar'><code>$xName(";
				$c = 0;
				$optional = 0;
				foreach ($parameters as $parameter) {
					$argsep = ",";
					if (isset($parameter['children']['argument'])) { # expand args
						$argsep = (isset($parameter['attributes']['argsep']) ? $parameter['attributes']['argsep'] : ",");
						foreach ($parameter['children']['argument'] as $argument) {
							$argName = $argument['attributes']['name'];
							$argRequired = (isset($argument['attributes']['required']) && $argument['attributes']['required'] === "true");
							$multiple = (isset($argument['attributes']['multiple']) && $argument['attributes']['multiple'] === "true");
							if (!$argRequired)
								$optional++;
							if ($c > 0)
								echo $argsep;
							if (!$argRequired)
								echo "[";
							else
								echo str_repeat("]", $optional);
							echo $argName;
							if ($multiple) {
								echo "[$argsep...]]"; # to match how the Asterisk wiki formats it
							}
							if ($argRequired)
								$optional = 0;
							$c++;
						}
					} else { /* this is for both options and other arguments */
						$argName = $parameter['attributes']['name'];
						$argRequired = (isset($parameter['attributes']['required']) && $parameter['attributes']['required'] === "true");
						if (!$argRequired)
							$optional++;
						if ($c > 0)
							echo $argsep;
						if (!$argRequired)
							echo "[";
						else
							echo str_repeat("]", $optional);
						echo $argName;
						if ($argRequired)
							$optional = 0;
						$c++;
					}
				}
				echo str_repeat("]", $optional);
				echo ")</code></p>";
			}
			if (isset($syntax['parameter'])) {
				echo "<h4>Arguments</h4>";
				echo "<ul>";
				$parameters = $syntax['parameter'];
				foreach ($parameters as $parameter) {
					$paramName = $parameter['attributes']['name'];
					$param = $parameter['children'];
					echo "<li><code>$paramName</code>";
					if (isset($param['argument'])) {
						echo "<ul>";
						foreach ($param['argument'] as $argument) {
							$argName = $argument['attributes']['name'];
							$argRequired = (isset($argument['attributes']['required']) && $argument['attributes']['required'] === "true");
							echo "<li><code>$argName</code>";
							if (isset($argument['children']['para'][0]['text'])) {
								echo " - ";
								foreach ($argument['children']['para'] as $para) {
									echo $para['text'] . "<br>";
								}
							}
							echo "</li>";
						}
						echo "</ul>";
					} else if (isset($param['optionlist'])) {
						echo "<ul>";
						foreach ($param['optionlist'][0]['children']['option'] as $option) {
							$optName = $option['attributes']['name'];
							$argsep = (isset($option['attributes']['argsep']) ? $option['attributes']['argsep'] : " <ERROR> ");
							$optRequired = (isset($option['attributes']['required']) && $option['attributes']['required'] === "true");
							echo "<li><code>$optName";
							if (isset($option['children']['argument'][0]['attributes']['name'])) {
								echo "( ";
								$c = 0;
								foreach ($option['children']['argument'] as $arg) {
									if ($c > 0)
										echo $argsep;
									$argRequired = (isset($arg['attributes']['required']) && $arg['attributes']['required'] === "true");
									if ($argRequired)
										echo "<b>";
									echo $arg['attributes']['name'];
									if ($argRequired)
										echo "</b>";
									$c++;
								}
								echo " )";
							}
							echo "</code>";
							if (isset($option['children']['para'][0]['text'])) {
								echo " - ";
								foreach ($option['children']['para'] as $para) {
									echo $para['text'] . "<br>";
								}
							}
							if (isset($option['children']['variablelist'][0]['children']['variable'])) {
								echo "<ul>";
								foreach ($option['children']['variablelist'][0]['children']['variable'] as $var) {
									$varName = $var['attributes']['name'];
									echo "<li><code>$varName</code>";
									if (isset($var['children']['para'])) {
										echo " - ";
										foreach ($var['children']['para'] as $para) {
											echo $para['text'] . "<br>";
										}
									}
									if (isset($var['children']['value'])) {
										echo "<ul>";
										foreach ($var['children']['value'] as $value) {
											$valueName = trim($value['attributes']['name']);
											$default = isset($value['attributes']['default']);
											$valueName = htmlspecialchars($valueName);
											echo "<li>" . strtoupper($valueName) . ($value['text'] && strlen($value['text']) > 0 ? " - " . $value['text'] : '');
											if ($default)
												echo " default: (true)";
											echo "</li>";
										}
										echo "</ul>";
									}
									echo "</li>";
								}
								echo "</ul>";
							}
							if (isset($option['children']['argument'])) {
								echo "<ul>";
								foreach ($option['children']['argument'] as $argument) {
									$argName = $argument['attributes']['name'];
									$argRequired = (isset($argument['attributes']['required']) && $argument['attributes']['required'] === "true");
									$argParams = (isset($argument['attributes']['hasparams']) && $argument['attributes']['required'] === "true");
									$argsep = (isset($argument['attributes']['argsep']) ? $argument['attributes']['argsep'] : " <ERROR> ");
									echo "<li><code>";
									if ($argRequired)
										echo "<b>";
									echo $argName;
									if ($argRequired)
										echo "</b>";
									if ($argParams)
										echo "( params )";
									echo "</code>";
									if (isset($argument['children']['para'][0]['text'])) {
										echo " - ";
										foreach ($argument['children']['para'] as $para) {
											echo $para['text'] . "<br>";
										}
									}
									if (isset($argument['children']['argument'][0])) {
										echo "<ul>";
										foreach ($argument['children']['argument'] as $subarg) {
											$subargName = $subarg['attributes']['name'];
											$multiple = (isset($subarg['attributes']['required']) && $subarg['attributes']['required'] === "true");
											$argRequired = (isset($subarg['attributes']['required']) && $subarg['attributes']['required'] === "true");
											echo "<li><code>";
											if ($argRequired)
												echo "<b>";
											echo $subargName;
											if ($argRequired)
												echo "</b>";
											if ($multiple) {
												echo "[$argsep$subargName...]";
											}
											echo "</code></li>";
										}
										echo "</ul>";
									}
									echo "</li>";
								}
								echo "</ul>";
							}
							/* options can contain an enumlist */
							if (isset($option['children']['enumlist'])) {
								foreach ($option['children']['enumlist'] as $enumlist) {
									print_enum_list($enumlist);
								}
							}
							echo "</li>";
						}
						echo "</ul>";
					} else if (isset($param['para'][0])) {
						echo " - ";
						foreach ($param['para'] as $para) {
							echo $para['text'] . "<br>";
						}
						/*
						 * as demonstrated by func_frame_drop, the current method of XML parsing has some serious disadvantages, namely that order is not
						 * preserved in any way. So if we have para, enumlist, para, enumlist (as in FRAME_DROP), then we get para, para, enumlist, enumlist,
						 * or enumlist, enumlist, para, para, neither of which makes any sense.
						 * (That is, order within a type, such as para or enum, is preserved, but not amongst them all interspersed together)
						 * However, until we have a better, improved way of XML parsing that preserves order, we should at least check for multiple children.
						 * Eventually, once we have an order-preserving parse, we can just call a callback function to print out each kind of element.
						 */
						if (isset($param['enumlist'][0]['children']['enum'])) { # xpointer not supported at this time
							foreach ($param['enumlist'] as $enumlist) {
								print_enum_list($enumlist);
							}
						}
					}
					echo "</li>";
				}
			}
			echo "</ul>";
		}
		if (isset($xData['configfile'])) {
			echo "<h3>Configuration Option Reference</h3>";
			foreach ($xData['configfile'] as $cf) {
				$cfName = $cf['attributes']['name'];
				echo "<h3>$cfName</h3>";
				foreach ($cf['children']['configobject'] as $configobj) {
					$coName = $configobj['attributes']['name'];
					echo "<h4>$coName</h4>";
					if (isset($configobj['children']['synopsis'])) {
						echo "<p>" . $configobj['children']['synopsis'][0]['text'] . "</p>";
					}
					if (isset($configobj['children']['configoption'])) {
						echo "<table>";
						echo "<tr><th>Option Name</th><th>Type</th><th>Default Value</th><th>Regular Expression</th><th>Description</th></tr>";
						foreach ($configobj['children']['configoption'] as $cfgOpt) {
							echo "<tr>";
							echo "<td>" . $cfgOpt['attributes']['name'] . "</td>";
							/*! \todo XML array doesn't contain these attributes? */
							echo "<td></td>";
							echo "<td></td>";
							echo "<td></td>";
							echo "<td>" . $cfgOpt['children']['synopsis'][0]['text'] . "</td>";
							echo "</tr>";
						}
						echo "</table>";
					}
				}
			}
		}
		if (isset($xData['see-also'][0])) {
			echo "<h3>See Also</h3>";
			echo "<ul>";
			foreach ($xData['see-also'][0]['children']['ref'] as $seealso) {
				$type = $seealso['attributes']['type'];
				$name = $seealso['text'];
				$linkName = $type;
				switch($type) {
					case 'manager':
						$linkName = "ManagerAction";
						break;
					case 'managerAction':
						$linkName = "ManagerAction";
						break;
					case 'managerEvent':
						$linkName = "ManagerEvent";
						break;
					case 'agi':
						$linkName = "AgiCommand";
						break;
					default:
						break;
				}
				$linkName = ucfirst($linkName);
				if ($type === "link") {
					echo "<li><a href='$name' target='_blank'>" . $name . "</a></li>";
				} else {
					echo "<li><a href='#" . strtolower($linkName) . "-$name'>" . $linkName . "_" . $name . "</a></li>";
				}
			}
			echo "</ul>";
		}
		echo "</div><hr>";
		echo PHP_EOL;
	}
}
function print_enum_list(array $enumlist) {
	echo "<ul>";
	foreach ($enumlist['children']['enum'] as $enum) {
		echo "<li><code>";
		echo $enum['attributes']['name'];
		echo "</code>";
		if (isset($enum['children']['para'][0]['text'])) {
			echo " - ";
			foreach ($enum['children']['para'] as $para) {
				echo $para['text'] . "<br>";
			}
		}
		if (isset($enum['children']['enumlist'])) {
			foreach ($enum['children']['enumlist'] as $enumlist) {
				print_enum_list($enumlist);
			}
		}
		echo "</li>";
	}
	echo "</ul>";
}
echo "</div></div></body></html>";
?>
