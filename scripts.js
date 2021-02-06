var selectedColor;
var occupation = {
	'AMA_DE_CASA': 'Ama de casa',
	'ESTUDIANTE': 'Estudiante',
	'EMPLEADO_PRIVADO': 'Empleado Privado',
	'EMPLEADO_ESTATAL': 'Empleado Estatal',
	'INDEPENDIENTE': 'Trabajador Independiente',
	'JUBILADO': 'Jubilado',
	'DESEMPLEADO': 'Desempleado'
};

$(document).ready(function () {
	$('.fixed-action-btn').floatingActionButton();
	$('.modal').modal();
	$('.tabs').tabs();
	$('.materialboxed').materialbox();

	M.FloatingActionButton.init($('.click-to-toggle'), {
		direction: 'left',
		hoverEnabled: false
	});

	if (typeof activeIcon != "undefined") {
		var menuItem = $($('.footer i')[activeIcon - 1]);
		menuItem.removeClass('grey-text');
		menuItem.removeClass('text-darken-3');
		menuItem.addClass('green-text');
	}

	$(window).resize(function () {
		return resizeImg();
	});

	showStateOrProvince();

	if ($('.container:not(#writeModal) > .row').length == 3) {
		var h = $('.container:not(#writeModal) > .row')[2].clientHeight + 8;
		$('.fixed-action-btn').css('bottom', h + 'px');
		$('#writeModal .actions').css('bottom', h - 8 + 'px');
	}

	$('#uploadPhoto').click(function (e) {
		if (typeof apretaste.loadImage != 'undefined') {
			apretaste.loadImage('onImageLoaded')
		} else {
			loadFileToBase64();
		}
	});
	var resizeInterval = setInterval(function () {
		// check until the img has the correct size
		resizeImg();
		if ($('#profile-rounded-img').css('background-size') != 'auto') clearTimeout(resizeInterval);
	}, 1);
	if (typeof notes != "undefined" || typeof chats != "undefined") $('#searchButton').removeClass('hide');
	if (typeof chats != "undefined" || typeof chat != "undefined") $('#chatButton').addClass('hide');

	if (typeof populars != "undefined" || $('#colors-nav').length > 0) {
		if ($('.container > .row').length != 3) $('.container> .row:first-child').css('margin-bottom', '15px');
	}

	$('#chat-row').parent().css('margin-bottom', '0');
});

function resizeImg() {
	if (typeof profile == "undefined") return;
	if ($('.container:not(#writeModal) > .row').length == 3) $('.container:not(#writeModal) > .row:first-child').css('margin-bottom', '0');
	var img = $('#profile-rounded-img');
	var size = $(window).height() / 4; // picture must be 1/4 of the screen

	img.height(size);
	img.width(size);
	img.css('top', -4 - $(window).height() / 8 + 'px'); // align the picture with the div

	$('#edit-fields').css('margin-top', -10 - $(window).height() / 8 + 'px'); // move the row before to the top to fill the empty space

	$('#img-pre').height(img.height() * 0.8); // set the height of the colored div after the photo
}

function getAvatar(avatar, serviceImgPath) {
	return "background-image: url(" + serviceImgPath + "/" + avatar + ".png);";
}

function getYears() {
	var year = new Date().getFullYear();
	var years = [];

	for (var i = year - 15; i >= year - 90; i--) {
		years.push(i);
	}

	return years;
}

function toggleWriteModal() {
	var status = $('#writeModal').attr('status');

	if (status == "closed") {
		if ($('.container:not(#writeModal) > .row').length == 3) {
			var h = $('.container:not(#writeModal) > .row')[0].clientHeight;
			$('#writeModal').css('height', 'calc(100% - ' + h + 'px)');
		}

		$('#writeModal').slideToggle({
			direction: "up"
		}).attr('status', 'opened');
		$('#note').focus();
	} else {
		hideKeyboard();
		$('#writeModal').slideToggle({
			direction: "up"
		}).attr('status', 'closed');
	}
}

function openSearchModal() {
	M.Modal.getInstance($('#searchModal')).open();
}

function replyUser(user) {
	user = user + ' '
	var comment = $('#comment');
	var currentComment = comment.val();

	if (currentComment.length === 0) comment.val('@' + user);
	else comment.val(currentComment + ' @' + user);
	M.Modal.getInstance($('#newCommentModal')).open();
	comment.focus();
}

function appendTag(tag) {
	tag = tag + ' '
	var content = $('#note');
	var currentContent = content.val();

	if (currentContent.length === 0) content.val('#' + tag);
	else content.val(currentContent + ' #' + tag);

	content.focus();
}

function openNote(id) {
	apretaste.send({'command': 'PIZARRA NOTA', 'data': {'note': id}});
}

var activeNote;

function sendNote() {
	hideKeyboard();
	var note = $('#note').val().trim();
	note = '' + note.replace(/ +(?= )/g, '')

	if (note.length > 2) {
		var files = notePicturePath != null ? [notePicturePath] : [];
		var basename = notePicturePath != null ? notePicturePath.split(/[\\/]/).pop() : null;

		apretaste.send({
			'command': 'PIZARRA ESCRIBIR',
			'data': {
				'text': note,
				'image': notePicture,
				'imageName': basename,
			},
			'files': files,
			'redirect': false,
			'callback': {
				'name': 'sendNoteCallback',
				'data': note
			}
		});
	} else {
		showToast('Minimo 3 caracteres');
	}
}

function sendComment() {
	hideKeyboard();
	var comment = $('#comment').val().trim();

	if (comment.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA COMENTAR',
			'data': {
				'comment': comment,
				'note': note.id
			},
			'redirect': false,
			'callback': {
				'name': 'sendCommentCallback',
				'data': comment.escapeHTML()
			}
		});
	} else {
		showToast('Escriba algo');
	}
}

function openReportModal() {
	M.Modal.getInstance($('#reportModal')).open();
	$('#reportMessage').focus();
}

function reportNote() {
	var message = $('#reportMessage').val().trim();

	if (message.length < 10) {
		showToast('Especifique la razon de su reporte');
		return;
	} else if (message.length > 250) {
		showToast('Mensaje demasiado largo');
		return;
	}

	apretaste.send({
		command: 'PIZARRA REPORTAR',
		data: {
			message: message,
			id: note.id
		},
		callback: {
			name: 'showToast',
			data: 'Reporte enviado'
		},
		redirect: false
	});
}

function searchText() {
	var search = $('#search').val().trim();

	if (search.length >= 2) {
		apretaste.send({
			'command': 'PIZARRA GLOBAL',
			'data': {
				search: search
			}
		});
	} else {
		showToast('Ingrese algo');
	}
}

function searchTopic(topic) {
	apretaste.send({
		'command': 'PIZARRA GLOBAL',
		'data': {
			search: '#' + topic
		}
	});
}

function searchUsername(username) {
	apretaste.send({
		command: 'pizarra global',
		data: {
			search: '@' + username
		}
	});
}

function deleteNote() {
	apretaste.send({
		'command': 'PIZARRA ELIMINAR',
		'data': {
			'note': activeNote
		},
		'redirect': false,
		callback: {
			'name': 'deleteCallback',
			'data': activeNote
		}
	});
}

function hideKeyboard() {
	if (
		document.activeElement &&
		document.activeElement.blur &&
		typeof document.activeElement.blur === 'function'
	) {
		document.activeElement.blur()
	}
}

function nextPage() {
	var command = title === 'Global' ? 'pizarra global' : 'pizarra';
	apretaste.send({
		command: command,
		data: {
			search: typeof search != 'undefined' ? search : null,
			page: page + 1
		}
	});
}

function previousPage() {
	var command = title === 'Global' ? 'pizarra global' : 'pizarra';
	apretaste.send({
		command: command,
		data: {
			search: typeof search != 'undefined' ? search : null,
			page: page - 1
		}
	});
}

function deleteCallback(id) {
	$('#' + id).remove();
	showToast('Nota eliminada');
}

function deleteNotification(id) {
	// delete from the backend
	apretaste.send({
		command: 'NOTIFICACIONES LEER',
		data: {
			id: id
		},
		redirect: false
	}); // remove from the view

	$('#' + id).fadeOut(function () {
		$(this).remove(); // show message if all notifications were deleted

		var count = $("ul.collection li").length;

		if (count <= 0) {
			var parent = $('#noti-list').parent();
			$('ul.collection').remove();
			parent.append("\n\t\t\t\t<div class=\"col s12 center\">\n\t\t\t\t<h1 class=\"black-text\">Nada por leer</h1>\n\t\t\t\t<i class=\"material-icons large\">notifications_off</i>\n\t\t\t\t<p>Por ahora usted no tiene ninguna notificaci\xF3n por leer.</p>\n\t\t\t\t</div>\n\t\t\t\t");
		}
	});
}

// submit the profile informacion


function submitProfileData() {
	if (myUser.id != profile.id) return; // get the array of fields and

	var fields = ['first_name', 'username', 'about_me', 'gender', 'year_of_birth', 'highest_school_level', 'country', 'province', 'city', 'usstate', 'religion', 'occupation']; // create the JSON of data

	var data = new Object();
	fields.forEach(function (field) {
		var value = $('#' + field).val();
		if (value && value.trim() != '' && !(field == "username" && value.trim() == '@' + profile.username)) data[field] = value;
	}); // save information in the backend

	apretaste.send({
		"command": "PERFIL UPDATE",
		"data": data,
		"redirect": false
	}); // show confirmation text

	M.toast({
		html: 'Su informacion se ha salvado correctamente'
	});
}

function noteLengthValidate() {
	var note = $('#note').val().trim();

	if (note.length <= 600) {
		$('.helper-text').html('Restante: ' + (600 - note.length));
	} else {
		$('.helper-text').html('Limite excedido');
	}
}

function commentLengthValidate() {
	var comment = $('#comment').val().trim();

	if (comment.length <= 250) {
		$('.helper-text').html(comment.length + '/' + '250');
	} else {
		$('.helper-text').html('Limite excedido');
	}
}

function remainder(size = 250) {
	// get message and remainder amount
	var comment = $('#comment').val().trim();
	var remainder = (comment.length <= size) ? (size - comment.length) : 0;

	// restrict comment size
	if (remainder <= 0) {
		comment = comment.substring(0, size);
		$('#comment').val(comment);
	}

	// update remainder amount
	$('#remainder').html(comment.length);
}

function reportLengthValidate() {
	var message = $('#reportMessage').val().trim();

	if (message.length <= 250) {
		$('#reportModal .helper-text').html(message.length + '/' + '250');
	} else {
		$('#reportModal .helper-text').html('Limite excedido');
	}
}

function like(id, type, pubType) {
	if (pubType === undefined || typeof pubType == 'undefined') {
		pubType = 'note';
	}

	var element = pubType == 'note' ? $('#' + id) : $('#comments #' + id);
	if (type == "like" && element.attr('liked') == 'true' || type == "unlike" && element.attr('unliked') == 'true') return;
	var data = pubType == 'note' ? {
		'note': id
	} : {
		'comment': id
	};
	apretaste.send({
		'command': 'PIZARRA ' + type,
		'data': data,
		'showLoading': false,
		'redirect': false
	});

	likeCallback({
		'id': id,
		'type': type,
		'pubType': pubType
	});
}

function likeCallback(data) {
	var id = data.id;
	var type = data.type;
	var pubType = data.pubType;
	var note = pubType == 'note' ? $('#' + id) : $('#comments #' + id);

	if (type == "like") {
		note.attr('liked', 'true');
		note.attr('unliked', 'false');
	} else {
		note.attr('unliked', 'true');
		note.attr('liked', 'false');
	}

	var counter = type == 'like' ? 'unlike' : 'like';
	var span = $('#' + id + ' span.' + type + ' span');
	var count = parseInt(span.html());
	span.html(count + 1);

	if ($('#' + id + ' span.' + counter).attr('onclick') == null) {
		span = $('#' + id + ' span.' + counter + ' span');
		count = parseInt(span.html());
		span.html(count - 1);
		$('#' + id + ' span.' + counter).attr('onclick', "like('" + id + "','" + counter + "', '" + pubType + "')");
	}

	$('#' + id + ' span.' + type).removeAttr('onclick');
}

function openProfile(username) {
	apretaste.send({
		'command': 'PERFIL',
		'data': {'username': '@' + username}
	});
}

var currentUser = null;
var currentUsername = null;

function addFriendModalOpen(id, username) {
	currentUser = id;
	currentUsername = username;
	$('.username').html('@' + username);
	M.Modal.getInstance($('#addFriendModal')).open();
}

function addFriend() {
	apretaste.send({
		command: 'amigos agregar',
		data: {id: currentUser},
		redirect: false,
		callback: {
			name: 'addFriendCallback'
		}
	});
}

function addFriendCallback() {
	showToast('Amistad aceptada');

	$('#' + currentUser + ' .action').html(
		'<a class="secondary-content second">' +
		'    <i class="fa fa-comment"' +
		'       onclick="openChat(\'' + currentUser + '\')">' +
		'    </i>' +
		'</a>' +
		'<a class="secondary-content third">' +
		'    <i class="fa fa-ban red-text"' +
		'       onclick="deleteModalOpen(\'' + currentUser + '\', \'' + currentUsername + '\')">' +
		'    </i>' +
		'</a>');
}

function deleteModalOpen(id, username) {
	currentUser = id;
	currentUsername = username;
	$('.username').html('@' + username);
	M.Modal.getInstance($('#deleteModal')).open();
}

function deleteFriend() {
	apretaste.send({
		command: 'amigos eliminar',
		data: {id: currentUser},
		redirect: false,
		callback: {
			name: 'deleteFriendCallback',
		}
	});
}

function deleteFriendCallback() {
	showToast('Amigo eliminado');

	$('#' + currentUser + ' .action').html(
		'<a class="secondary-content second">' +
		'    <i class="fa fa-user-plus green-text"' +
		'       onclick="addFriendModalOpen(\'' + currentUser + '\', \'' + currentUsername + '\')">' +
		'    </i>' +
		'</a>');
}

function openChat(id) {
	apretaste.send({
		command: 'chat',
		data: {
			id: id
		}
	});
}


// Callback functions

function sendCommentCallback(comment) {
	var avatar = 'face="' + myUser.avatar + '"';
	if (myUser.isInfluencer) {
		var serviceImgPath = $('serviceImgPath').attr('data');
		avatar += ' creator_image="' + serviceImgPath + myUser.username + '.png" state="gold"'
	}

	var element =
		"<li class=\"right\" id=\"last\">\n" +
		"    <div class=\"person-avatar circle\" " + avatar + " color=\"" + myUser.avatarColor + "\"\n" +
		"         size=\"30\" onclick=\"openProfile('" + myUser.username + "')\"></div>\n" +
		"    <div class=\"head\">\n" +
		"        <a onclick=\"openProfile('" + myUser.username + "')\"\n" +
		"           class=\"" + myUser.gender + "\">@" + myUser.username + "</a>\n" +
		"        <span class=\"date\">" + moment().format('MMM D, YYYY h:mm A') + "</span>\n" +
		"    </div>\n" +
		"    <span class=\"text\" style=\"word-break: break-word;\">" + comment + "</span>\n" +
		"</li>"

	$('#no-comments').remove();

	$('#comments').append(element);
	$('#comment').val('');
	$('html, body').animate({
		scrollTop: $("#last").offset().top - 64
	}, 1000);

	$('#newCommentModal .helper-text').html('0/250');

	$('.person-avatar').each(function (i, item) {
		item.innerHTML = '';
		setElementAsAvatar(item)
	});

	toggleWriteModal();
}

function sendNoteCallback(note) {
	var serviceImgPath = $('serviceImgPath').attr('data');
	var topics = note.match(/(^|\B)#(?![0-9_]+\b)([a-zA-Z0-9_]{1,30})(\b|\r)/g);
	var htmlTopics = "";
	topics = topics != null ? topics.splice(0, 3) : [myUser.topic];

	var hasImage = "";
	if (typeof notePicture != "undefined" || (typeof notePicturePath != "undefined" && notePicturePath != null)) {
		var src = "data:image/jpg;base64," + notePicture;
		if (notePicturePath != null) src = "file://" + notePicturePath;

		if (typeof apretaste.showImage != 'undefined' && notePicturePath != null) {
			hasImage = "<img class=\"responsive-img\" style=\"width: 100%\" src=\"" + src + "\" onclick=\"apretaste.showImage('" + src + "')\">";
		} else {
			hasImage = "<img class=\"responsive-img\" style=\"width: 100%\" src=\"" + src + "\" onclick=\"apretaste.send({'command': 'PIZARRA NOTA','data':{'note':'last'}});\">";
		}

		$('#notePicture').remove();
		notePicture = null;
		notePicturePath = null;
	}

	var avatar = 'face="' + myUser.avatar + '"';
	if (myUser.isInfluencer) {
		avatar += ' creator_image="' + serviceImgPath + myUser.username + '.png" state="gold"'
	}

	topics.forEach(function (topic) {
		topic = topic.replace('#', '');
		htmlTopics +=
			'<div class="chip small" onclick="apretaste.send({\'command\': \'PIZARRA GLOBAL\',\'data\':{\'search\':\'#' + topic + '\'}})">' +
			'    <i class="fa fa-hashtag"></i>' + topic +
			'</div>';
	});
	note = note.escapeHTML();

	var element =
		'<div class="card note" id="last" liked="false"\n' +
		'                 unliked="false">\n' +
		'                <div class="card-person grey lighten-5">\n' +
		'                        <div class="person-avatar circle left"\n' + avatar +
		'                             color="' + myUser.avatarColor + '"\n' +
		'                             size="30" online="1">\n' +
		'                        </div>\n' +
		'                        <a href="#!" class="' + myUser.gender + '"\n' +
		'                           onclick="apretaste.send({\'command\': \'PERFIL\', \'data\': {\'username\':\'' + myUser.username + '\'}})">\n' +
		'                            @' + myUser.username + '\n' +
		'                        </a>\n' +
		'                    <span class="chip tiny clear right">\n' +
		'                        <i class="material-icons icon">perm_contact_calendar</i>\n' +
		moment().format('MMM D, h:mm A') + '\n' +
		'                    </span>\n' +
		'                </div>\n' +
		'                <div class="card-content">\n' +
		hasImage +
		'                    <p>' + note + '</p>\n' +
		'                    <div class="tags">\n' +
		htmlTopics +
		'                    </div>\n' +
		'                </div>\n' +
		'                <div class="card-action grey lighten-4">\n' +
		'                        <span class="chip like" style="background-color: transparent; padding-left: 0;"\n' +
		'                        onclick="like(\'last\',\'like\');">' +
		'                            <i class="material-icons icon">thumb_up</i>\n' +
		'                            <span>0</span>\n' +
		'                        </span>\n' +
		'                        <span class="chip unlike" style="background-color: transparent;"\n' +
		'                        onclick="like(\'last\',\'unlike\')">' +
		'                            <i class="material-icons icon">thumb_down</i>\n' +
		'                            <span>0</span>\n' +
		'                        </span>\n' +
		'                    <span class="chip" style="background-color: transparent;"\n' +
		'                          onclick="apretaste.send({\'command\': \'PIZARRA NOTA\',\'data\':{\'note\':\'last\'}});">\n' +
		'                        <i class="material-icons icon">comment</i>\n' +
		'                        <span>0</span>\n' +
		'                    </span>\n' +
		'                </div>\n' +
		'            </div>';

	$('.notes > .col').prepend(element);
	showToast('Nota publicada');
	$('#note').val('');
	toggleWriteModal();

	var avatarElement = $('#last .person-avatar');
	avatarElement.innerHTML = '';
	setElementAsAvatar(avatarElement);

	$('html, body').animate({
		scrollTop: $("#last").offset().top
	}, 1000);
}

function togglePopularsMenu() {
	var option1 = $('#populars-nav div:nth-child(1) h5');
	var option2 = $('#populars-nav div:nth-child(2) h5');
	var option1content = $('#popular-users');
	var option2content = $('#popular-topics');

	if (option1.hasClass('green-text')) {
		option1.removeClass('green-text');
		option1.addClass('black-text');
		option2.attr('onclick', '');
		option1.attr('onclick', 'togglePopularsMenu()');
		option2.removeClass('black-text');
		option2.addClass('green-text');
		option1content.fadeOut();
		option2content.fadeIn();
	} else {
		option2.removeClass('green-text');
		option2.addClass('black-text');
		option1.attr('onclick', '');
		option2.attr('onclick', 'togglePopularsMenu()');
		option1.removeClass('black-text');
		option1.addClass('green-text');
		option2content.fadeOut();
		option1content.fadeIn();
	}
}

var notePicture;
var notePicturePath = null;

function onImageLoaded(path) {
	showLoadedImage(path);
	notePicturePath = path;
}

function showLoadedImage(source) {
	if ($('#notePicture').length === 0) {
		$('#writeModal > .row > .col').append('<img id="notePicture" class="responsive-img"/>');
	}

	$('#notePicture').attr('src', source);
}

function sendFile(base64File) {
	notePicture = base64File;
	var notePictureSrc = "data:image/jpg;base64," + base64File;

	showLoadedImage(notePictureSrc)
}

function showToast(text) {
	M.toast({
		html: text
	});
}

String.prototype.firstUpper = function () {
	return this.charAt(0).toUpperCase() + this.substr(1).toLowerCase();
};

String.prototype.replaceAll = function (search, replacement) {
	return this.split(search).join(replacement);
}; // get list of countries to display


function getCountries() {
	return [{
		code: 'cu',
		name: 'Cuba'
	}, {
		code: 'us',
		name: 'Estados Unidos'
	}, {
		code: 'es',
		name: 'Espana'
	}, {
		code: 'it',
		name: 'Italia'
	}, {
		code: 'mx',
		name: 'Mexico'
	}, {
		code: 'br',
		name: 'Brasil'
	}, {
		code: 'ec',
		name: 'Ecuador'
	}, {
		code: 'ca',
		name: 'Canada'
	}, {
		code: 'vz',
		name: 'Venezuela'
	}, {
		code: 'al',
		name: 'Alemania'
	}, {
		code: 'co',
		name: 'Colombia'
	}, {
		code: 'OTRO',
		name: 'Otro'
	}];
}

var province = {
	'PINAR_DEL_RIO': 'Pinar del Río',
	'ARTEMISA': 'Artemisa',
	'LA_HABANA': 'La Habana',
	'MAYABEQUE': 'Mayabeque',
	'MATANZAS': 'Matanzas',
	'CIENFUEGOS': 'Cienfuegos',
	'VILLA_CLARA': 'Villa Clara',
	'SANCTI_SPIRITUS': 'Sancti Spíritus',
	'CIEGO_DE_AVILA': 'Ciego de Ávila',
	'CAMAGUEY': 'Camagüey',
	'LAS_TUNAS': 'Las Tunas',
	'GRANMA': 'Granma',
	'HOLGUIN': 'Holguín',
	'SANTIAGO_DE_CUBA': 'Santiago de Cuba',
	'GUANTANAMO': 'Guantánamo',
	'ISLA_DE_LA_JUVENTUD': 'Isla de la Juventud'
};

function showStateOrProvince() {
	var country = $('#country').val();
	var province = $('.province-div');
	var usstate = $('.usstate-div');

	switch (country) {
		case 'cu':
			province.show();
			usstate.hide();
			break;

		case 'us':
			usstate.show();
			province.hide();
			break;

		default:
			usstate.hide();
			province.hide();
			break;
	}
}

String.prototype.escapeHTML = function () {
	var htmlEscapes = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#x27;',
		'/': '&#x2F;'
	};
	var htmlEscaper = /[&<>"'\/]/g;
	return ('' + this).replace(htmlEscaper, function (match) {
		return htmlEscapes[match];
	});
};

// Convert links in text
window.linkify = (function () {
	var
		SCHEME = "[a-z\\d.-]+://",
		IPV4 = "(?:(?:[0-9]|[1-9]\\d|1\\d{2}|2[0-4]\\d|25[0-5])\\.){3}(?:[0-9]|[1-9]\\d|1\\d{2}|2[0-4]\\d|25[0-5])",
		HOSTNAME = "(?:(?:[^\\s!@#$%^&*()_=+[\\]{}\\\\|;:'\",.<>/?]+)\\.)+",
		TLD = "(?:ac|ad|aero|ae|af|ag|ai|al|am|an|ao|aq|arpa|ar|asia|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|biz|bi|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|cat|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|coop|com|co|cr|cu|cv|cx|cy|cz|de|dj|dk|dm|do|dz|ec|edu|ee|eg|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gov|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|info|int|in|io|iq|ir|is|it|je|jm|jobs|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mg|mh|mil|mk|ml|mm|mn|mobi|mo|mp|mq|mr|ms|mt|museum|mu|mv|mw|mx|my|mz|name|na|nc|net|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|org|pa|pe|pf|pg|ph|pk|pl|pm|pn|pro|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tel|tf|tg|th|tj|tk|tl|tm|tn|to|tp|travel|tr|tt|tv|tw|tz|ua|ug|uk|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|xn--0zwm56d|xn--11b5bs3a9aj6g|xn--80akhbyknj4f|xn--9t4b11yi5a|xn--deba0ad|xn--g6w251d|xn--hgbk6aj7f53bba|xn--hlcj6aya9esc7a|xn--jxalpdlp|xn--kgbechtv|xn--zckzah|ye|yt|yu|za|zm|zw)",
		HOST_OR_IP = "(?:" + HOSTNAME + TLD + "|" + IPV4 + ")",
		PATH = "(?:[;/][^#?<>\\s]*)?",
		QUERY_FRAG = "(?:\\?[^#<>\\s]*)?(?:#[^<>\\s]*)?",
		URI1 = "\\b" + SCHEME + "[^<>\\s]+",
		URI2 = "\\b" + HOST_OR_IP + PATH + QUERY_FRAG + "(?!\\w)",

		MAILTO = "mailto:",
		EMAIL = "(?:" + MAILTO + ")?[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*@" + HOST_OR_IP + QUERY_FRAG + "(?!\\w)",

		URI_RE = new RegExp("(?:" + URI1 + "|" + URI2 + "|" + EMAIL + ")", "ig"),
		SCHEME_RE = new RegExp("^" + SCHEME, "i"),

		quotes = {
			"'": "`",
			'>': '<',
			')': '(',
			']': '[',
			'}': '{',
			'»': '«',
			'›': '‹'
		},

		default_options = {
			callback: function (text, href) {
				return href ? '<a href="' + href + '" title="' + href + '">' + text + '</a>' : text;
			},
			punct_regexp: /(?:[!?.,:;'"]|(?:&|&amp;)(?:lt|gt|quot|apos|raquo|laquo|rsaquo|lsaquo);)$/
		};

	return function (txt, options) {
		options = options || {};

		// Temp variables.
		var arr,
			i,
			link,
			href,

			// Output HTML.
			html = '',

			// Store text / link parts, in order, for re-combination.
			parts = [],

			// Used for keeping track of indices in the text.
			idx_prev,
			idx_last,
			idx,
			link_last,

			// Used for trimming trailing punctuation and quotes from links.
			matches_begin,
			matches_end,
			quote_begin,
			quote_end;

		// Initialize options.
		for (i in default_options) {
			if (options[i] === undefined) {
				options[i] = default_options[i];
			}
		}

		// Find links.
		while (arr = URI_RE.exec(txt)) {

			link = arr[0];
			idx_last = URI_RE.lastIndex;
			idx = idx_last - link.length;

			// Not a link if preceded by certain characters.
			if (/[\/:]/.test(txt.charAt(idx - 1))) {
				continue;
			}

			// Trim trailing punctuation.
			do {
				// If no changes are made, we don't want to loop forever!
				link_last = link;

				quote_end = link.substr(-1)
				quote_begin = quotes[quote_end];

				// Ending quote character?
				if (quote_begin) {
					matches_begin = link.match(new RegExp('\\' + quote_begin + '(?!$)', 'g'));
					matches_end = link.match(new RegExp('\\' + quote_end, 'g'));

					// If quotes are unbalanced, remove trailing quote character.
					if ((matches_begin ? matches_begin.length : 0) < (matches_end ? matches_end.length : 0)) {
						link = link.substr(0, link.length - 1);
						idx_last--;
					}
				}

				// Ending non-quote punctuation character?
				if (options.punct_regexp) {
					link = link.replace(options.punct_regexp, function (a) {
						idx_last -= a.length;
						return '';
					});
				}
			} while (link.length && link !== link_last);

			href = link;

			// Add appropriate protocol to naked links.
			if (!SCHEME_RE.test(href)) {
				href = (href.indexOf('@') !== -1 ? (!href.indexOf(MAILTO) ? '' : MAILTO)
					: !href.indexOf('irc.') ? 'irc://'
						: !href.indexOf('ftp.') ? 'ftp://'
							: 'http://')
					+ href;
			}

			// Push preceding non-link text onto the array.
			if (idx_prev != idx) {
				parts.push([txt.slice(idx_prev, idx)]);
				idx_prev = idx_last;
			}

			// Push massaged link onto the array
			parts.push([link, href]);
		}
		;

		// Push remaining non-link text onto the array.
		parts.push([txt.substr(idx_prev)]);

		// Process the array items.
		for (i = 0; i < parts.length; i++) {
			html += options.callback.apply(window, parts[i]);
		}

		// In case of catastrophic failure, return the original text;
		return html || txt;
	};

})();