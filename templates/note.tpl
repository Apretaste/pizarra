{include file="../includes/appmenu.tpl"}

{assign var="color" value="black-text"}
{if $note['gender'] eq "M"}{assign var="color" value="blue-text"}{/if}
{if $note['gender'] eq "F"}{assign var="color" value="pink-text"}{/if}

<div class="row">
	<div class="card white">
		<div class="card-content">
			<span class="{$color}">{link href="PIZARRA PERFIL @{$note['username']}" caption="@{$note['username']}"}</span>&middot;
			<small class="grey-text">{$note['location']}</small>&middot;
			<small class="grey-text">{$note['inserted']|date_format:"%b %e, %I:%M %p"|capitalize}</small>
			{if $note['online']}&nbsp;&nbsp;<span class="online">ONLINE</span>{/if}
			<div class="divider"></div>
			{space5}
			<p style="word-break: break-word;">{$note['text']|replace_url}</p>
			{space5}
			<small>
				{foreach from=$note['topics'] item=topic}
					{link href="PIZARRA {$topic['name']}" caption="#{$topic['name']}" style="color:gray;"}&nbsp;
				{/foreach}
			</small>
		</div>
		<div class="card-action">
			<span class="emoji">{link href="PIZARRA LIKE {$note['id']}" caption="&#128077; {$note['likes']}" wait="false"}</span>
			<span class="emoji">{link href="PIZARRA UNLIKE {$note['id']}" caption="&#x1F44E; {$note['unlikes']}" wait="false"}</span>
			{if $note['canmodify']}
				{if count($note['topics']) < 3}
				<span><b>{link href="PIZARRA TEMIFICAR {$note['id']}" popup="true" wait="false" caption="#" desc="A que #tema pertenece esta nota?*"}</b></span>
				{/if}
				<span class="emoji">{link href="PIZARRA ELIMINAR {$note['id']}" caption="❌" wait="true"}</span>
			{/if}
		</div>
	</div>
</div>

{if $note['comments']}
<ul class="collection">
{foreach from=$note['comments'] item=comment}
{assign var="color" value="black-text"}
{if $comment['gender'] eq "M"}{assign var="color" value="blue-text"}{/if}
{if $comment['gender'] eq "F"}{assign var="color" value="pink-text"}{/if}
	<li class="collection-item">
	<span class="{$color}">{link href="PIZARRA PERFIL @{$comment['username']}" caption="@{$comment['username']}"}</span>&middot;
	<small class="grey-text">{$comment['location']}</small>&middot;
	<small class="grey-text">{$comment['inserted']|date_format:"%b %e, %I:%M %p"|capitalize}</small>
	<p style="margin:0; word-break: break-word;">{$comment['text']}</p>
	</li>
{/foreach}
</ul>
{/if}
<center>
	{button href="PIZARRA COMENTAR {$note['id']} " caption="Comentar" popup="true" size="small" wait="true" desc="a:Escriba un comentario para esta nota*"}
</center>
