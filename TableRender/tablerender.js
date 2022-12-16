var isRelationLocked = false;
var lockedRelationship = 0;

$(document).ready(() => {
	$('.annotation').hover(displayRelationship, clearRelationship);
	populateDocumentMenu(documentRelations);
});

function dimAnnotations() {
	if (!isRelationLocked)
		$(".annotation").addClass("inactive");
}

function restoreAnnotations() {
	if (!isRelationLocked)
		$(".annotation").removeClass("inactive").removeClass("active");
}

function displayRelationship(e) {
	if (isRelationLocked)
		return;
	dimAnnotations();
	let elem = e.target;
	let annot_id = elem.getAttribute("data-annot-id");
	$(elem).removeClass("inactive").addClass("active");
	documentRelations.forEach((relation) => {
		let nodes = relation["nodes"];
		let isAnnotUsed = nodes.filter(node => node["refid"] == annot_id, annot_id).length > 0;
		if (isAnnotUsed) {
			nodes.forEach(node => {
				let nextTarget = $('div').find('[data-annot-id=' + node["refid"] + ']');
				$(elem).connections({to: nextTarget});
				$(nextTarget).removeClass("inactive").addClass("active");
			});
		}
	}, annot_id);
}

function clearRelationship() {
	if (isRelationLocked)
		return;
	$('connection').remove();
	restoreAnnotations();
	$("#relationship-info").remove();
}

function lockRelationship(relationshipIndex) {
	if (isRelationLocked)
		clearLock();
	lockedRelationship = relationshipIndex;
	isRelationLocked = true;
	let relationshipNodes = documentRelations[relationshipIndex]["nodes"];
	let elem = $('div').find('[data-annot-id=' + relationshipNodes[0]["refid"] + ']');
	$(elem).removeClass("inactive").addClass("active");
	for (let i = 1; i < relationshipNodes.length; i++) {
		let nextTarget = $('div').find('[data-annot-id=' + relationshipNodes[i]["refid"] + ']');
		$(elem).connections({to: nextTarget});
		$(nextTarget).removeClass("inactive").addClass("active");
	}
	loadRelationshipInfo(relationshipIndex);
}

function clearLock() {
	isRelationLocked = false;
	lockedRelationship = 0;
	clearRelationship();
}

function loadRelationshipInfo(index) {
	$("#relationship-info").remove();
	$("#relationship-btn-" + index).after(
		`<ul id='relationship-info'>
			<li>` + documentRelations[index]["infons"]["annotator"] + `</li>
				<li>` + documentRelations[index]["infons"]["updated_at"] + `</li>
		</ul>`
	);
}

function navigateToRelationship(index) {
	let target_id = documentRelations[index]["nodes"][0]["refid"];
	let target = $('div').find('[data-annot-id=' + target_id + ']');
	target[0].scrollIntoView();
	lockRelationship(index);
}

function populateDocumentMenu(relations) {
	// documentRelations passed via PHP
	for (let i = 0, out_i = 1;i < relations.length; i++, out_i++) {
		let type = relations[i]["infons"]["type"];
		$("#document-relations").append(
			"<li id='relationship-btn-"+i+"' onClick='navigateToRelationship(\"" + i + "\")'>" + out_i + " - " + type + "</li>"
		);
	}
}