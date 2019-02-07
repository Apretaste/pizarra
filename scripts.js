$(document).ready(function(){
    $('.fixed-action-btn').floatingActionButton();
    $('.modal').modal();
    $('.materialboxed').materialbox();
    M.FloatingActionButton.init($('.click-to-toggle'), {
        direction: 'left',
        hoverEnabled: false
    });

    //For main profile
    if (typeof profile!="undefined" && typeof isMyOwnProfile!="undefined") {
        if(isMyOwnProfile){
            $("#editar").click(function() {
                return apretaste.send({"command": "PERFIL EDITAR"})
            });
        }else{
            $("#chat").click(function() {
                apretaste.send({"command": 'CHAT', data: {"username":profile.username}});
            });

            $("#bloquear").click(function() {
                apretaste.send({"command": 'PERFIL BLOQUEAR', data: {"username":profile.username}});
            });
        }
        $("#notas").click(function() {
            apretaste.send({"command": 'PIZARRA', data: {"search":'@'+profile.username}});
        });
    }
});

var activeNote;

function sendNote() {
    let note = $('#note').val().trim();
    if(note.length>=20){
        apretaste.send({
            'command':'PIZARRA ESCRIBIR',
            'data':{'text':note},
            'redirect':false,
            'callback':{'name':'sendNoteCallback','data':note}
        });
    }
    else showToast('Minimo 20 caracteres');
}

function sendComment() {
    let comment = $('#comment').val().trim();
    if(comment.length>=2){
        apretaste.send({
            'command':'PIZARRA COMENTAR',
            'data':{'comment':comment,'note':note.id},
            'redirect':false,
            'callback':{'name':'sendCommentCallback','data':comment}
        });
    }
    else showToast('Escriba algo');
}

function searchText(){
    let search = $('#search').val().trim();
    if(search.length>=2){
        apretaste.send({
            'command': 'PIZARRA',
            'data':{'search':search}
        });
    }
    else showToast('Ingrese algo');
}

function deleteNote(){
    apretaste.send({
        'command': 'PIZARRA ELIMINAR',
        'data':{'note':activeNote},
        'redirect':false, 
        callback:{'name':'deleteCallback','data':activeNote}
    });
}

function deleteCallback(id) {
    $('#'+id).remove();
    showToast('Nota eliminada');
}

function themifyNote(){
    let theme = $('#theme').val().trim();
    if(theme.length>=2){
        apretaste.send({
            'command': 'PIZARRA TEMIFICAR',
            'data':{'note':activeNote,'theme':theme},
            'redirect':false,
            'callback':{'name':'themifyCallback','data':theme}
        });
    }
    else showToast('Ingrese algo');
}

function noteLengthValidate() {
    let note = $('#note').val().trim();
    if(note.length<=300) $('.helper-text').html('Restante: '+(300-note.length));
    else $('.helper-text').html('Limite excedido');
}

function commentLengthValidate() {
    let comment = $('#comment').val().trim();
    if(comment.length<=250) $('.helper-text').html('Restante: '+(250-comment.length));
    else $('.helper-text').html('Limite excedido');
}

function like(id, type){
    apretaste.send({
        'command': 'PIZARRA '+type,
        'data':{'note':id},
        'callback':{
            'name':'likeCallback',
            'data':{'id':id,'type':type}
        },
        'redirect':false
    });
}

function likeCallback(data){
    id = data.id;
    type = data.type;
    counter = type=='like'?'unlike':'like';
    let span = $('#'+id+' a.'+type+' span');
    let count = parseInt(span.html());
    span.html(count+1);
    if($('#'+id+' a.'+counter).attr('onclick')==null){
        span = $('#'+id+' a.'+counter+' span');
        count = parseInt(span.html());
        span.html(count-1);
        $('#'+id+' a.'+counter).attr('onclick', "like('"+id+"','"+counter+"')");
    }
    $('#'+id+' a.'+type).removeAttr('onclick');
}

function sendCommentCallback(comment) {
    if(myGender=="M") color="blue-text"; else if(myGender=="F") color="pink-text"; else color="black-text";
    let element = `<li class="collection-item">
        <a class="`+color+`" onclick="apretaste.send({'command': 'PIZARRA PERFIL', 'data': {'username':'@`+myUsername+`'}});">
            <b>@`+myUsername+`</b>
        </a>&middot;
        <small class="grey-text">`+myLocation+`</small>&middot;
        <small class="grey-text">`+new Date(Date.now()).toLocaleString()+`</small>
        <p>`+comment+`</p>
    </li>`;

    $('#comments').append(element);
    showToast('Comentario enviado');
}

function sendNoteCallback(note) {
    if(myGender=="M") color="blue-text"; else if(myGender=="F") color="pink-text"; else color="black-text";
    let element = `
    <div class="row" id="last">
        <div class="card white">
            <div class="card-content">
                <a class="`+color+`" onclick="apretaste.send({'command': 'PIZARRA PERFIL', 'data': {'username':'`+myUsername+`'}});">
                    <b>@`+myUsername+`</b>
                </a>
                &nbsp;<i class="tiny material-icons green-text">brightness_1</i>
                &middot;
                <small class="grey-text text-darken-2">`+myLocation+`</small>&middot;
                <small class="grey-text text-darken-2">`+new Date(Date.now()).toLocaleString()+`</small>
                <div class="divider"></div>
                <p>`+note+`</p>
                <small class="topics">
                    <a class="grey-text text-darken-2" onclick="apretaste.send({'command': 'PIZARRA','data':{'search':'`+defaultTopic+`'}})">
                        `+defaultTopic+`
                    </a>&nbsp;
                </small>
            </div>
            <div class="card-action">
                <a class="like" onclick="like('last','like');">
                    <i class="material-icons tiny">thumb_up</i>
                    <span >0</span>
                </a>
                <a class="unlike" onclick="like('last','unlike')">
                    <i class="material-icons tiny">thumb_down</i>
                    <span>0</span>	
                </a>
                <a onclick="apretaste.send({'command': 'PIZARRA NOTA','data':{'note':'last'}});">
                    <i class="material-icons tiny">comment</i>
                    <span>0</span>
                </a>
                <a class="modal-trigger" href="#themifyModal" onclick="activeNote = 'last';">
                    <span><b>#</b></span>
                </a>
                <a class="modal-trigger" href="#deleteConfirmModal" onclick="activeNote = 'last';">
                    <i class="material-icons tiny">cancel</i>
                </a>
            </div>
        </div>
    </div>
    `;

    $('#notes').prepend(element);
    showToast('Nota publicada');
}

function themifyCallback(theme){
    $('#'+activeNote+' .topics').append(`
    <a class="grey-text text-darken-2" onclick="apretaste.send({'command': 'PIZARRA','data':{'search':'`+theme+`'}})">
        #`+theme+`
    </a>&nbsp;`);

    if($('#'+activeNote+' .topics').children().length==3) $('#'+activeNote+' .themifyButton').remove();
}

function showToast(text){
    M.toast({html: text});
}