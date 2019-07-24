$(document).ready(function () {
	$('.fixed-action-btn').floatingActionButton();
	$('.modal').modal();
	$('.materialboxed').materialbox();
	M.FloatingActionButton.init($('.click-to-toggle'), {
		direction: 'left',
		hoverEnabled: false
	});

	//For main profile
	if (typeof profile != "undefined" && typeof isMyOwnProfile != "undefined") {
		if (isMyOwnProfile) {
			$("#editar").click(function () {
				return apretaste.send({"command": "PERFIL EDITAR"})
			});
		}
		else {
			$("#chat").click(function () {
				apretaste.send({
					"command": 'CHAT',
					data: {"username": profile.username}
				});
			});

			$("#bloquear").click(function () {
				apretaste.send({
					"command": 'PERFIL BLOQUEAR',
					data: {"username": profile.username}
				});
			});
		}
		$("#notas").click(function () {
			apretaste.send({
				"command": 'PIZARRA',
				data: {"search": '@' + profile.username}
			});
		});
	}

	if(typeof activeIcon != "undefined"){
		var menuItem = $($('.footer i')[activeIcon-1]);
		menuItem.removeClass('grey-text');
		menuItem.removeClass('text-darken-3');
		menuItem.addClass('pizarra-color-text');
	}

	if($('.container:not(#writeModal) > .row').length == 3){
		var h = $('.container:not(#writeModal) > .row')[2].clientHeight+8;
		$('.fixed-action-btn').css('bottom', h+'px');
	}
});

function toggleWriteModal() {
	var status = $('#writeModal').attr('status');

	if(status == "closed") {
		if($('.container:not(#writeModal) > .row').length == 3){
			var h = $('.container:not(#writeModal) > .row')[0].clientHeight;
			$('#writeModal').css('height', 'calc(100% - '+h+'px)');
		}

		$('#writeModal').slideToggle({direction: "up"}).attr('status', 'opened'); //, () => resizeImg() // add this to resize then opened or closed
	} else {
		$('#writeModal').slideToggle({direction: "up"}).attr('status', 'closed'); //, () => resizeImg()
	}
}

var activeNote;

function sendNote() {
	let note = $('#note').val().trim();
	if (note.length >= 20) {
		apretaste.send({
			'command': 'PIZARRA ESCRIBIR',
			'data': {'text': note},
			'redirect': false,
			'callback': {'name': 'sendNoteCallback', 'data': note}
		});
	}
	else {
		showToast('Minimo 20 caracteres');
	}
}

function sendComment() {
	let comment = $('#comment').val().trim();
	if (comment.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA COMENTAR',
			'data': {'comment': comment, 'note': note.id},
			'redirect': false,
			'callback': {'name': 'sendCommentCallback', 'data': comment}
		});
	}
	else {
		showToast('Escriba algo');
	}
}

function searchText() {
	let search = $('#search').val().trim();
	if (search.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA',
			'data': {'search': search}
		});
	}
	else {
		showToast('Ingrese algo');
	}
}

function deleteNote() {
	apretaste.send({
		'command': 'PIZARRA ELIMINAR',
		'data': {'note': activeNote},
		'redirect': false,
		callback: {'name': 'deleteCallback', 'data': activeNote}
	});
}

function deleteCallback(id) {
	$('#' + id).remove();
	showToast('Nota eliminada');
}

function themifyNote() {
	let theme = $('#theme').val().trim();
	if (theme.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA TEMIFICAR',
			'data': {'note': activeNote, 'theme': theme},
			'redirect': false,
			'callback': {'name': 'themifyCallback', 'data': theme}
		});
	}
	else {
		showToast('Ingrese algo');
	}
}

function noteLengthValidate() {
	let note = $('#note').val().trim();
	if (note.length <= 300) {
		$('.helper-text').html('Restante: ' + (300 - note.length));
	}
	else {
		$('.helper-text').html('Limite excedido');
	}
}

function commentLengthValidate() {
	let comment = $('#comment').val().trim();
	if (comment.length <= 250) {
		$('.helper-text').html('Restante: ' + (250 - comment.length));
	}
	else {
		$('.helper-text').html('Limite excedido');
	}
}

function like(id, type) {
	apretaste.send({
		'command': 'PIZARRA ' + type,
		'data': {'note': id},
		'callback': {
			'name': 'likeCallback',
			'data': JSON.stringify({'id': id, 'type': type})
		},
		'redirect': false
	});
}

function likeCallback(data) {
	data = JSON.parse(data)
	id = data.id;
	type = data.type;
	counter = type == 'like' ? 'unlike' : 'like';
	let span = $('#' + id + ' a.' + type + ' span');
	let count = parseInt(span.html());
	span.html(count + 1);
	if ($('#' + id + ' a.' + counter).attr('onclick') == null) {
		span = $('#' + id + ' a.' + counter + ' span');
		count = parseInt(span.html());
		span.html(count - 1);
		$('#' + id + ' a.' + counter).attr('onclick', "like('" + id + "','" + counter + "')");
	}
	$('#' + id + ' a.' + type).removeAttr('onclick');
}

function sendCommentCallback(comment) {
	if (myUser.gender == "M") {
		color = "pizarra-color-text";
	}
	else if (myUser.gender == "F") {
		color = "pink-text";
	}
	else {
		color = "black-text";
	}
	let element = `<li class="collection-item">
        <a class="` + color + `" onclick="apretaste.send({'command': 'PIZARRA PERFIL', 'data': {'username':'@` + myUser.username + `'}});">
            <b>@` + myUser.username + `</b>
        </a>&middot;
        <small class="grey-text">` + myLocation + `</small>&middot;
        <small class="grey-text">` + new Date(Date.now()).toLocaleString() + `</small>
        <p>` + comment + `</p>
    </li>`;

	$('#comments').append(element);
	showToast('Comentario enviado');

	$('html, body').animate({
		scrollTop: $("li:last-of-type").offset().top
	}, 1000);
}

function sendNoteCallback(note) {
	if (myUser.gender == "M") {
		color = "pizarra-color-text";
	}
	else if (myUser.gender == "F") {
		color = "pink-text";
	}
	else {
		color = "black-text";
	}

	let serviceImgPath = $('serviceImgPath').attr('data');
	let topics = note.match(/(^|\B)#(?![0-9_]+\b)([a-zA-Z0-9_]{1,30})(\b|\r)/g);
	let htmlTopics = "";
	topics = topics.splice(0, 3);

	topics.forEach(function(topic){
		topic = topic.replace('#','');
		htmlTopics += `
			<a onclick="apretaste.send({'command': 'PIZARRA','data':{'search':'`+topic+`'}})">
				<b>#`+topic+`</b>
			</a>&nbsp;`;
	});

	let element = `
	<li class="collection-item avatar row" id="last">
		<img src="`+serviceImgPath+myUser.avatar+`.png" alt="Avatar" class="circle">
		<span class="title">
			<a class="`+color+`" onclick="apretaste.send({'command': 'PIZARRA PERFIL', 'data': {'username':'`+myUser.username+`'}});">
				<b>@`+myUser.username+`</b>
			</a>
			<small class="grey-text text-darken-3">`+myUser.location+` · `+new Date(Date.now()).toLocaleString()+`</small>
		</span>
		
		<p>` + note + `</p>
		<p>
			`+htmlTopics+`
			</p>
			
			<div class="col s10" id="note-actions">
				<div class="col s4">
					<a class="like" onclick="like('last','like');">
						<i class="material-icons">thumb_up</i>
						<span>0</span>
					</a>
				
				</div>
					<div class="col s4">
						<a class="unlike" onclick="like('last','unlike')">
							<i class="material-icons">thumb_down</i>
							<span>o</span>
						</a>
					</div>
				<div class="col s4">
					<a onclick="apretaste.send({'command': 'PIZARRA NOTA','data':{'note':'last'}});">
						<i class="material-icons">comment</i>
						<span>0</span>
					</a>
				</div>
			</div>
		</li>`;

	$('#notes .collection').prepend(element);
	showToast('Nota publicada');

	$('#note').val('');
	toggleWriteModal();

	$('html, body').animate({
		scrollTop: $("#last").offset().top
	}, 1000);
}

function themifyCallback(theme) {
	$('#' + activeNote + ' .topics').append(`
    <a class="grey-text text-darken-2" onclick="apretaste.send({'command': 'PIZARRA','data':{'search':'` + theme + `'}})">
        #` + theme + `
    </a>&nbsp;`);

	if ($('#' + activeNote + ' .topics').children().length == 3) {
		$('#' + activeNote + ' .themifyButton').remove();
	}
}

function showToast(text) {
	M.toast({html: text});
}