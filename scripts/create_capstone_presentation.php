<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$output = $root.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'capstone-rfid-system-presentation.pptx';

$slides = [
    ['RFID Attendance And School Operations System', ['Full capabilities demonstration', 'Attendance, face verification, inventory, registrar biometrics, portal, Messenger, online classes, clinic, emergency, reports']],
    ['Presentation Goal', ['Show the complete system by role', 'Explain the correct setup order', 'Demonstrate instructor verification', 'Demonstrate attendance, portal, Messenger, excuse letters, emergency, inventory, and reports']],
    ['System Users', ['Root Admin', 'Admin', 'Registrar', 'Instructor', 'Console Panel User', 'Student', 'Parent', 'Clinic']],
    ['Recommended Demo Order', ['Admin setup', 'Registrar enrollment', 'Instructor login and verification', 'Console attendance panel', 'Student/parent portal', 'Messenger, excuse letters, emergency, inventory, reports']],
    ['Admin Capabilities', ['Laboratories and active devices', 'Academic records: strands, sections, subjects, schedules', 'Users, instructors, students, parent links', 'Inventory records', 'Attendance logs, online classes, reports, settings, Messenger']],
    ['Admin Setup Demonstration', ['Login as admin', 'Show laboratories and active devices', 'Show strands, sections, and subjects', 'Show instructors and students', 'Confirm people and academics exist before schedules']],
    ['Schedule Setup Demonstration', ['Open Schedules', 'Select laboratory, instructor, section, and subject', 'Set weekday, start time, and end time', 'Save schedule', 'Mention manual overlap checking']],
    ['Inventory Capabilities', ['Create and review inventory records', 'Track item names, categories, quantity, status, and location', 'Support admin visibility and reporting', 'This demo covers inventory records only']],
    ['Registrar Capabilities', ['Student RFID enrollment', 'Student face image enrollment', 'Instructor RFID enrollment', 'Instructor face image enrollment', 'Registrar reports and Messenger']],
    ['Registrar Demonstration', ['Login as registrar', 'Open Student Biometric Enrollment', 'Show student RFID and face images', 'Open Instructor Face Enrollment', 'Show instructor RFID and face images']],
    ['Instructor Capabilities', ['Instructor dashboard', 'Extra verification before protected access', 'Assigned schedules and students', 'Online classes', 'Attendance participation, approvals, logs, reports, Messenger']],
    ['Instructor Login Verification', ['Verification page: /instructor/verify', 'Facial verification using enrolled instructor face', 'Email OTP', 'Security questions', 'Verified instructor redirects to dashboard']],
    ['Instructor Verification Demo', ['Login as instructor', 'Show Face Verification option', 'Show OTP send and OTP entry', 'Show Security Question setup or answer', 'Continue to instructor dashboard after success']],
    ['Console Panel Capabilities', ['Room selection', 'Instructor RFID session start', 'Student face/fallback verification', 'Student RFID tap recording', 'Temporary movement and Student Logout support', 'Emergency alert creation']],
    ['Attendance Panel Demonstration', ['Login as console', 'Select laboratory', 'Instructor taps RFID to start class', 'Student completes verification', 'Student taps RFID', 'Panel records check-in, late, temporary movement, or checkout']],
    ['Attendance Rules Demonstration', ['First valid tap is check-in', 'Late uses admin threshold', 'Temporary exit/return needs instructor approval', 'Checkout window starts 15 minutes before end', 'Extra taps after checkout are ignored but logged']],
    ['Student Capabilities', ['Portal dashboard', 'Attendance history and evidence links when authorized', 'Online classes', 'Excuse letters with attachments', 'Messenger, notifications, profile and password updates']],
    ['Parent Capabilities', ['Linked student dashboard', 'Linked student switching', 'Attendance viewing', 'Excuse letter approval', 'Parent-created signed letters', 'Messenger and notifications']],
    ['Student And Parent Portal Demo', ['Login as student', 'Show dashboard, attendance, online classes, excuse letters', 'Create excuse letter', 'Login as parent', 'Approve and download approved letter']],
    ['Messenger Capabilities', ['Unified Messenger for authenticated roles', 'Search users', 'Start or open conversation', 'Send messages and attachments', 'Student sends message and instructor replies']],
    ['Online Class Capabilities', ['Instructor/admin create online classes', 'Meeting link and instructions', 'Optional face requirement', 'Student join tracking', 'Notifications and audit logs']],
    ['Excuse Letter Capabilities', ['Student creates excuse letter', 'Optional attachment upload', 'Parent approval required for student-created letters', 'Parent can create signed letters', 'Approved letter download']],
    ['Emergency And Clinic Capabilities', ['Emergency alert creation from attendance panel', 'Clinic dashboard alert monitoring', 'Emergency types and hotlines', 'Case logs and patient histories', 'Clinic reports']],
    ['Emergency Demonstration', ['Trigger emergency alert from panel', 'Login as clinic', 'Review alert details', 'Update alert status', 'Create clinic case and patient history', 'Show clinic reports']],
    ['Reports And Audit Capabilities', ['Shared role-aware reports', 'CSV export', 'Activity logs', 'Online class audit logs', 'Registrar enrollment logs', 'Accountability without sensitive payloads']],
    ['Full Demo Closing Sequence', ['Admin setup records', 'Registrar enrollment', 'Instructor verification by face, OTP, or security question', 'Panel attendance tap', 'Portal, Messenger, excuse letters, emergency, inventory, reports']],
    ['Known Limitations', ['No schedule overlap detection', 'No dedicated setup wizard', 'No academic term closing/archive workflow', 'No attendance correction approval workflow', 'Live SMS/text dispatch depends on external provider readiness']],
    ['Conclusion', ['Complete role-based school operations workflow', 'Strong RFID attendance core', 'Biometric verification, portal self-service, clinic response, inventory visibility, messaging, reports, and auditability']],
];

function xml_text(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function slide_xml(string $title, array $bullets): string
{
    $bulletParagraphs = '';
    foreach ($bullets as $bullet) {
        $bulletParagraphs .= '<a:p><a:pPr marL="342900" indent="-171450"><a:buChar char="&#8226;"/></a:pPr><a:r><a:rPr lang="en-US" sz="2600"/><a:t>'.xml_text($bullet).'</a:t></a:r></a:p>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:cSld>
    <p:spTree>
      <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
      <p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
        <p:spPr><a:xfrm><a:off x="685800" y="457200"/><a:ext cx="7772400" cy="914400"/></a:xfrm></p:spPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="en-US" sz="3900" b="1"/><a:t>'.xml_text($title).'</a:t></a:r></a:p></p:txBody>
      </p:sp>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="3" name="Content"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr><p:ph type="body" idx="1"/></p:nvPr></p:nvSpPr>
        <p:spPr><a:xfrm><a:off x="914400" y="1600200"/><a:ext cx="7315200" cy="4343400"/></a:xfrm></p:spPr>
        <p:txBody><a:bodyPr/><a:lstStyle/>'.$bulletParagraphs.'</p:txBody>
      </p:sp>
    </p:spTree>
  </p:cSld>
  <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>
</p:sld>';
}

@unlink($output);
$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Unable to create {$output}\n");
    exit(1);
}

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>
  <Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>
  <Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>
  <Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>';
foreach ($slides as $i => $_) {
    $contentTypes .= "\n  <Override PartName=\"/ppt/slides/slide".($i + 1).".xml\" ContentType=\"application/vnd.openxmlformats-officedocument.presentationml.slide+xml\"/>";
}
$contentTypes .= "\n</Types>";

$presentationRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
$slideIds = '';
foreach ($slides as $i => $_) {
    $n = $i + 1;
    $presentationRels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide'.$n.'.xml"/>';
    $slideIds .= '<p:sldId id="'.(256 + $n).'" r:id="rId'.$n.'"/>';
}
$layoutRid = count($slides) + 1;
$masterRid = count($slides) + 2;
$themeRid = count($slides) + 3;
$presentationRels .= '<Relationship Id="rId'.$masterRid.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/></Relationships>';

$presentation = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId'.$masterRid.'"/></p:sldMasterIdLst>
  <p:sldIdLst>'.$slideIds.'</p:sldIdLst>
  <p:sldSz cx="9144000" cy="5143500" type="screen16x9"/>
  <p:notesSz cx="6858000" cy="9144000"/>
</p:presentation>';

$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>');
$zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Codex</Application><PresentationFormat>On-screen Show (16:9)</PresentationFormat><Slides>'.count($slides).'</Slides></Properties>');
$zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>RFID Borrowing And Attendance System</dc:title><dc:creator>Codex</dc:creator><cp:lastModifiedBy>Codex</cp:lastModifiedBy></cp:coreProperties>');
$zip->addFromString('ppt/presentation.xml', $presentation);
$zip->addFromString('ppt/_rels/presentation.xml.rels', $presentationRels);
$zip->addFromString('ppt/slideMasters/slideMaster1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld><p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/><p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst><p:txStyles><p:titleStyle/><p:bodyStyle/><p:otherStyle/></p:txStyles></p:sldMaster>');
$zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/></Relationships>');
$zip->addFromString('ppt/slideLayouts/slideLayout1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="titleAndBody" preserve="1"><p:cSld name="Title and Content"><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sldLayout>');
$zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/></Relationships>');
$zip->addFromString('ppt/theme/theme1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Capstone"><a:themeElements><a:clrScheme name="Capstone"><a:dk1><a:srgbClr val="111827"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="1F2937"/></a:dk2><a:lt2><a:srgbClr val="F9FAFB"/></a:lt2><a:accent1><a:srgbClr val="0F766E"/></a:accent1><a:accent2><a:srgbClr val="2563EB"/></a:accent2><a:accent3><a:srgbClr val="DC2626"/></a:accent3><a:accent4><a:srgbClr val="CA8A04"/></a:accent4><a:accent5><a:srgbClr val="16A34A"/></a:accent5><a:accent6><a:srgbClr val="7C3AED"/></a:accent6><a:hlink><a:srgbClr val="2563EB"/></a:hlink><a:folHlink><a:srgbClr val="7C3AED"/></a:folHlink></a:clrScheme><a:fontScheme name="Aptos"><a:majorFont><a:latin typeface="Aptos Display"/></a:majorFont><a:minorFont><a:latin typeface="Aptos"/></a:minorFont></a:fontScheme><a:fmtScheme name="Default"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst><a:lnStyleLst><a:ln w="9525"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme></a:themeElements></a:theme>');

foreach ($slides as $i => [$title, $bullets]) {
    $n = $i + 1;
    $zip->addFromString('ppt/slides/slide'.$n.'.xml', slide_xml($title, $bullets));
    $zip->addFromString('ppt/slides/_rels/slide'.$n.'.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/></Relationships>');
}

$zip->close();

echo "Created {$output}\n";
