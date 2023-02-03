<?php
define('CKP_CMD', 1);
define('CKP_ALL_PARAMS', 2);
define('CKP_FIRST_PARAM', 3);
define('CKP_OTHER_PARAMS', 4); // all params except the first one

// Extension credits that will show up on Special:Version    
$wgExtensionCredits['parserhook'][] = array(
	'name'         => 'CKPatch syntax-highlighting',
	'version'      => '1.0',
	'author'       => 'Malvineous', 
//	'url'          => 'http://www.mediawiki.org/wiki/Extension:MyExtension',
	'description'  => 'Add &lt;patch&gt; tag to colour-code content according to CKPatch rules'
);
					 
//Avoid unstubbing $wgParser on setHook() too early on modern (1.12+) MW versions, as per r35980
if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
	$wgHooks['ParserFirstCallInit'][] = 'efPatchParserInit';
} else { // Otherwise do things the old fashioned way
	$wgExtensionFunctions[] = 'efPatchParserInit';
}

$wgHooks['ParserAfterTidy'][] = 'efPatchParserAfterTidy';

function efPatchParserInit()
{
	global $wgParser;
	$wgParser->setHook( 'patch', 'efPatchRender' );
	
	return true;
}
 
function efPatchRender($input, $args, $parser)
{
	global $ckpatch_markerList;

	$aMatches = array();
	if (preg_match('/((.*?)(^|' . "\n" . ')(%end|%abort|$))(.*$)?/s', trim($input), $aMatches)) {
		// Had an %end or %abort that was matched
		$input = $aMatches[1];
		$strTrailingIgnored = $aMatches[5];
	} else {
		// else no %end or %abort, work on the whole string
		$input = trim($input); // but we still need to trim it!
		$strTrailingIgnored = '';
	}

	/* The comment code using <div> instead of <span> is a dodgy hack that gets
	   fixed up by HTMLtidy.  Because the comments are within parameters due to
		 the way CKPatch parses them, you end up with code like this, if you have
		 commands within comments:
		 
		 <first_command>%some_command
		 <comment># Start of comment </first_command><second_command>%commented_command</comment>
		 </second_command>
		 
		 If these are all spans they happen to work out nicely and you get
		 highlighting within comments, but to avoid that some other non-span tag
		 has to be used, and then HTMLtidy cleans up the tag order to produce
		 correct code.
	*/
	
	$aRules = array(
		'/(?<!\\\\)(#.*)$/m' => '<div class="codeComment">\1</div>',
		'/(?<!\\\\)\[(.*?)(?<!\\\\)]/m' => '<span class="codeHighlight">\1</span>',
		'/(?<!\\\\)\{(.*?)(?<!\\\\)}/m' => '<span class="codeHighlight2">\1</span>',
//		'/(?<!\\\\)(%.+?)(?=%|$)/es' => 'ckpHighlightCommand("\1")',
		'/(?<!\\\\)(%.+?)(?=(?<!\\\\)%|$)/es' => 'ckpHighlightCommand(\'\1\')',
		'/^(%end)(\s+.*)?$/m' => '<span class="codeCommand">\1</span><span class="codeBadParam">\2</span>',
//		'/^(.*?\S.*?)(%end\s)(.*)?$/m' => '\1<span class="codeUnknownCommand">\2</span><span class="codeUnknownParam">\3</span>'
	);

	$strOutput = '
<table class="wikitable ckpatch">
<thead>
	<tr><td>Patch: ' . $args['title'] . '</td></tr>
</thead>
<tbody>
	<tr><td><pre class="ckpatch">
';
/*$strOutput .= preg_replace(
	array(
		'/(?<!\\\\)(#.*)$/m',
		'/(?<!\\\\)\[(.*?)(?<!\\\\)]/m',
		'/(?<!\\\\)\{(.*?)(?<!\\\\)}/m',
		'/(?<!\\\\)(%.+?)(?=(?<!\\\\)%|$)/es',
		'/^(%end)(\s+.*)?$/m',
//		'/^(.*?\S.*?)(%end\s)(.*)?$/m'
	),
	array(
		'<div class="codeComment">\1</div>',
		'<span class="codeHighlight">\1</span>',
		'<span class="codeHighlight2">\1</span>',
		'ckpHighlightCommand(\'\1\')',
		'<span class="codeCommand">\1</span><span class="codeBadParam">\2</span>',
//		'\1<span class="codeUnknownCommand">\2</span><span class="codeUnknownParam">\3</span>'
	),
	htmlspecialchars('%patch $0F07                               $B8 [$41 $01] $83 $3E {$94 $AA} [$00 $74] # Draw the joystick (Check $AA94 if 0 use xx')
);*/
	
	$strOutput .= //htmlspecialchars(
		preg_replace(
			array_keys($aRules),
			array_values($aRules),
			htmlspecialchars($input)
		) //)
	;
	
	if (!empty($strTrailingIgnored)) {
		$strOutput .= '<span class="codeExtra">' . $strTrailingIgnored . '</span>';
	}
	
	$strOutput .= '</pre></td></tr></table>';

	$iCount = count($ckpatch_markerList);
	$ckpatch_markerList[$iCount] = $strOutput;
	return 'xx-ckpatch_marker' . $iCount . '-xx';
}

function efPatchParserAfterTidy(&$parser, &$text) {
	// find markers in $text
	// replace markers with actual output
	global $ckpatch_markerList;
	$k = array();
	for ($i = 0; $i < count($ckpatch_markerList); $i++)
		$k[] = 'xx-ckpatch_marker' . $i . '-xx';
	//$text = preg_replace('/xx-ckpatch_marker' . $i . '-xx/', $markerList[$i], $text);
	//$text = preg_replace($k, $ckpatch_markerList, $text);
	$text = str_replace($k, $ckpatch_markerList, $text);
	return true;
}

function ckpHighlightCommand($strCommand)
{
	// Cancel out escaping from preg_replace()'s "e" eval modifier
//	$strCommand = str_replace("\\'", "'", $strCommand);
	$strCommand = str_replace('\"', '"', $strCommand);
	
	$aMatches = array();
	preg_match('/\S(\s+$)/', $strCommand, $aMatches);
	@$strTrailingWhitespace = $aMatches[1];
	// Don't need to worry about leading whitespace as there will never be any
	// thanks to the regex that called us.

	//if ($strCommand[0] != '%') return "aar!"; else return 'x';
	$aMatches = array();
//	preg_match('/^([^\s]+)((\s+[^\s]+\s+)?(.*$))/s', trim($strCommand), $aMatches);
	preg_match('/^([^\s]+)((\s+[^\s]+)(\s+.*)?)?$/s', trim($strCommand), $aMatches);

	//foreach ($aMatches as &$m) $m = str_replace("\n", '\n', $m);
	//return '<div style="border: 1px solid red;">' . $strCommand . '</div>' . htmlspecialchars(print_r($aMatches, true));

	$strOut = '<span class="codeCommand">' . $aMatches[CKP_CMD] . '</span>';
	$aCmdIgnore = array('%end'); // ignore these, they're handled in the main regex block
	$aCmdNoParam = array('%abort'); // TODO: comment everything after %end?
	$aCmdSingleFilename = array(
		'%audio',
		'%audiodct',
		'%audiohed',
		'%ckmhead.obj',
		'%egadict',
		'%egagraph',
		'%egahead',
		'%egalatch',
		'%egasprit',
		'%gamemaps',
		'%level.dir',
		'%maphead'
	);
	$aCmdSingleString = array(
		'%ext',
		'%version'
	);
	$aCmdIntString = array(
		'%level.entry',
		'%level.hint',
		'%level.name',
		'%patch',
		'%patchfile'
	);
	// Special cases handled individually
	// %level.file - int filename
	// %dump - filename int int
	
	if (in_array($aMatches[CKP_CMD], $aCmdIgnore)) {
		return $strCommand;


	} else if (in_array($aMatches[CKP_CMD], $aCmdNoParam)) {
		if (!empty($aMatches[CKP_ALL_PARAMS])) {
			// If this is a no-parameter command and we've got a param, colour it bad
			$strOut .= '<span class="codeBadParam">' . $aMatches[CKP_ALL_PARAMS] . '</span>';
		} // else do nothing, the command will be printed as-is
	
	
	} else if (in_array($aMatches[CKP_CMD], $aCmdSingleString)) {
		if (!empty($aMatches[CKP_FIRST_PARAM])) {
			// If this is a single-parameter command and we've got a param, colour it
			$strOut .= '<span class="codeString">' . $aMatches[CKP_FIRST_PARAM] . '</span>';
		}
		if (!empty($aMatches[CKP_OTHER_PARAMS])) {
			// If this is a single-parameter command and we've got a param, colour it
			$strOut .= '<span class="codeBadParam">' . $aMatches[CKP_OTHER_PARAMS] . '</span>';
		}
		
	
	
	} else if (in_array($aMatches[CKP_CMD], $aCmdSingleFilename)) {
		if (!empty($aMatches[CKP_FIRST_PARAM])) {
			// If this is a single-parameter command and we've got a param, colour it
			$strOut .= '<span class="codeFilename">' . $aMatches[CKP_FIRST_PARAM] . '</span>';
		}
		if (!empty($aMatches[CKP_OTHER_PARAMS])) {
			// If this is a single-parameter command and we've got a param, colour it
			$strOut .= '<span class="codeBadParam">' . $aMatches[CKP_OTHER_PARAMS] . '</span>';
		}
		
	
	
	} else if (in_array($aMatches[CKP_CMD], $aCmdIntString)) {
		if (!empty($aMatches[CKP_FIRST_PARAM])) {
			// If this is a single-parameter command and we've got a param, colour it
			$strOut .= '<span class="codeInt">' . $aMatches[CKP_FIRST_PARAM] . '</span>';
		}
		if (!empty($aMatches[CKP_OTHER_PARAMS])) {
			// If this is a single-parameter command and we've got a param, colour it
			$strOut .= '<span class="codeString">' . $aMatches[CKP_OTHER_PARAMS] . '</span>';
		}
		
	
	
	} else if ($aMatches[CKP_CMD] == '%level.file') {
		if (!empty($aMatches[CKP_FIRST_PARAM])) {
			// If this is a single-parameter command and we've got a param, colour it
			$strOut .= '<span class="codeInt">' . $aMatches[CKP_FIRST_PARAM] . '</span>';
		}
		if (!empty($aMatches[CKP_OTHER_PARAMS])) {
			$p = array();
			preg_match('/^(\s*\S+\s*)(.*)$/', $aMatches[CKP_OTHER_PARAMS], $p);
			if (count($p) > 1) {
				// Multiple params after the level number
				$strOut .= '<span class="codeFilename">' . $p[1] . '</span>' .
					'<span class="codeBadParam">' . $p[2] . '</span>';
			} else {
				// Just the one parameter after the level number
				$strOut .= '<span class="codeFilename">' . $aMatches[CKP_OTHER_PARAMS] . '</span>';
			}
		}


	} else if ($aMatches[CKP_CMD] == '%dump') {
		if (!empty($aMatches[CKP_FIRST_PARAM])) {
			// If this is a single-parameter command and we've got a param, colour it
			$strOut .= '<span class="codeFilename">' . $aMatches[CKP_FIRST_PARAM] . '</span>';
		}
		if (!empty($aMatches[CKP_OTHER_PARAMS])) {
			// If this is a single-parameter command and we've got a param, colour it
			$strOut .= '<span class="codeInt">' . $aMatches[CKP_OTHER_PARAMS] . '</span>';
		}


	} else {
		return '<span class="codeUnknownCommand">' . $aMatches[CKP_CMD] . '</span>' .
			(!empty($aMatches[CKP_ALL_PARAMS]) ? '<span class="codeUnknownParam">' . $aMatches[CKP_ALL_PARAMS] . '</span>' : '') .
			$strTrailingWhitespace
		;
	}
	return $strOut . $strTrailingWhitespace;
}

?>