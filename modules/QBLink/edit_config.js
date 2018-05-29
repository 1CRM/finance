function checkCurrentStep(form) {
	return true;
}

function fixState(id, enabled) {
	obj = document.getElementById(id);
	if(! obj)
		return;
	if(enabled)
		obj.disabled = false;
	else {
		obj.disabled = true;
		obj.checked = false;
	}
}

function updateSyncOpts(form) {
	chk1 = document.getElementById('chkImport_Customers');
	chk2 = document.getElementById('chkImport_Products');
	if(! chk1 || ! chk2)
		return;
	canedit = chk1.checked && chk2.checked;
	fixState('chkImport_Estimates', canedit);
	fixState('chkImport_Invoices', canedit);

	chk1 = document.getElementById('chkImport_Vendors');
	chk2 = document.getElementById('chkImport_Products');
	if(! chk1 || ! chk2)
		return;
	canedit = chk1.checked && chk2.checked;
	fixState('chkImport_Bills', canedit);

	chk1 = document.getElementById('chkExport_Customers');
	chk2 = document.getElementById('chkExport_Products');
	if(! chk1 || ! chk2)
		return;
	canedit = chk1.checked && chk2.checked;
	fixState('chkExport_Quotes', canedit);
	fixState('chkExport_Invoices', canedit);

	chk1 = document.getElementById('chkExport_Vendors');
	chk2 = document.getElementById('chkExport_Products');
	if(! chk1 || ! chk2)
		return;
	canedit = chk1.checked && chk2.checked;
	fixState('chkExport_Bills', canedit);
}
