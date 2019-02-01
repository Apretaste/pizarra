$(document).ready(function(){
    $('.fixed-action-btn').floatingActionButton();
    $('.modal').modal();
});

var activeNote;

function sendNote() {
    let note = $('#note').val().trim();
    if(note.length>=20){
        apretaste.send({
            'command':'PIZARRA ESCRIBIR',
            'data':{'text':note},
            'redirect':false,
            'callback':{'name':'showToast','data':'Su nota ha sido publicada'}
        });
    }
    else showToast('Minimo 20 caracteres');
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
    if(search.length>=2){
        apretaste.send({'command': 'PIZARRA TEMIFICAR','data':{'note':activeNote,'theme':theme},'redirect':false});
    }
    else showToast('Ingrese algo');
}

function noteLengthValidate() {
    let note = $('#note').val().trim();
    if(note.length<=300) $('.helper-text').html('Restante: '+(300-note.length));
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

function showToast(text){
    M.toast({html: text});
}