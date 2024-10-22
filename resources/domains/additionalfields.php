<?php

$additionaldomainfields[".au"][] = array("Name" => "Registrant Name", "LangVar" => "autldregname", "Type" => "text", "Size" => "20", "Default" => "", "Required" => true,);
$additionaldomainfields[".au"][] = array("Name" => "Registrant ID", "LangVar" => "autldregid", "Type" => "text", "Size" => "20", "Default" => "", "Required" => true,);
$additionaldomainfields[".au"][] = array("Name" => "Registrant ID Type", "LangVar" => "autldregidtype", "Type" => "dropdown", "Options" => "ABN,ACN,Business Registration Number,Other", "Default" => "ABN",);
$additionaldomainfields[".au"][] = array("Name" => "Eligibility Name", "LangVar" => "autldeligname", "Type" => "text", "Size" => "20", "Default" => "", "Required" => false,);
$additionaldomainfields[".au"][] = array("Name" => "Eligibility ID", "LangVar" => "autldeligid", "Type" => "text", "Size" => "20", "Default" => "", "Required" => false,);
$additionaldomainfields[".au"][] = array("Name" => "Eligibility ID Type", "LangVar" => "autldeligidtype", "Type" => "dropdown", "Options" => ",Australian Company Number (ACN),ACT Business Number,NSW Business Number,NT Business Number,QLD Business Number,SA Business Number,TAS Business Number,VIC Business Number,WA Business Number,Trademark (TM),Other - Used to record an Incorporated Association number,Australian Business Number (ABN)", "Default" => "",);
$additionaldomainfields[".au"][] = array("Name" => "Eligibility Type", "LangVar" => "autldeligtype", "Type" => "dropdown", "Options" => "Charity,Citizen/Resident,Club,Commercial Statutory Body,Company,Incorporated Association,Industry Body,Non-profit Organisation,Other,Partnership,Pending TM Owner  ,Political Party,Registered Business,Religious/Church Group,Sole Trader,Trade Union,Trademark Owner,Child Care Centre,Government School,Higher Education Institution,National Body,Non-Government School,Pre-school,Research Organisation,Training Organisation", "Default" => "Company",);
$additionaldomainfields[".au"][] = array("Name" => "Eligibility Reason", "LangVar" => "autldeligreason", "Type" => "radio", "Options" => "Domain name is an Exact Match Abbreviation or Acronym of your Entity or Trading Name.,Close and substantial connection between the domain name and the operations of your Entity.", "Default" => "Domain name is an Exact Match Abbreviation or Acronym of your Entity or Trading Name.",);

// Exclude fields that are not relevant to id.au
$additionaldomainfields[".id.au"][] = array("Name" => "Registrant Name", "LangVar" => "autldregname", "Type" => "text", "Size" => "20", "Default" => "", "Required" => true,);
$additionaldomainfields[".id.au"][] = array("Name" => "Eligibility Type", "LangVar" => "autldeligtype", "Type" => "dropdown", "Options" => ",Citizen/Resident", "Required" => true,);
$additionaldomainfields[".id.au"][] = array("Name" => "Registrant ID", "Remove" => true);
$additionaldomainfields[".id.au"][] = array("Name" => "Registrant ID Type", "Remove" => true,);
$additionaldomainfields[".id.au"][] = array("Name" => "Eligibility Name", "Remove" => true);
$additionaldomainfields[".id.au"][] = array("Name" => "Eligibility ID", "Remove" => true);
$additionaldomainfields[".id.au"][] = array("Name" => "Eligibility ID Type", "Remove" => true);
$additionaldomainfields[".id.au"][] = array("Name" => "Eligibility Reason", "Remove" => true);
