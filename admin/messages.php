<?php
require_once __DIR__.'/../app/Security.php';
require_once __DIR__.'/../app/Content.php';
admin_required();

$pdo = Database::pdo();
if (isset($_GET['read'])) {
    $pdo->prepare('UPDATE messages SET is_read=1 WHERE id=?')->execute([(int)$_GET['read']]);
    header('Location: messages.php');
    exit;
}

$rows = $pdo->query('SELECT * FROM messages ORDER BY id DESC')->fetchAll();
require_once __DIR__.'/_header.php';
?>
<h1>Messages</h1>
<div class="table messages-table"><table>
<tr><th>Date</th><th>Name</th><th>Email</th><th>Message</th><th>Status</th></tr>
<?php foreach($rows as $r): ?>
<tr class="message-row <?= $r['is_read'] ? 'is-read' : 'is-unread' ?>" tabindex="0" role="button" data-message-id="<?=e($r['id'])?>" data-date="<?=e($r['created_at'])?>" data-name="<?=e($r['name'])?>" data-email="<?=e($r['email'])?>" data-message="<?=e($r['message'])?>">
<td><?=e($r['created_at'])?></td>
<td><strong><?=e($r['name'])?></strong></td>
<td><?=e($r['email'])?></td>
<td><span class="message-preview"><?=e($r['message'])?></span></td>
<td><?=$r['is_read'] ? '<span class="message-status read">Read</span>' : '<span class="message-status unread">Unread</span>'?></td>
</tr>
<?php endforeach; ?>
</table></div>

<div class="message-modal" id="messageModal" hidden aria-hidden="true">
  <div class="message-modal-backdrop" data-close-message></div>
  <div class="message-modal-card" role="dialog" aria-modal="true" aria-labelledby="messageModalTitle">
    <div class="message-modal-head"><div><h2 id="messageModalTitle">Message</h2><span id="messageModalDate"></span></div><button type="button" class="message-modal-close" data-close-message aria-label="Tutup">×</button></div>
    <div class="message-meta"><div><small>Nama</small><strong id="messageModalName"></strong></div><div><small>Email</small><strong id="messageModalEmail"></strong></div></div>
    <div class="message-body" id="messageModalBody"></div>
    <div class="message-modal-actions"><a id="messageReply" class="message-reply" href="#">Balas via Email</a><button type="button" class="message-close-btn" data-close-message>Tutup</button></div>
  </div>
</div>
<script>
(function(){
 const modal=document.getElementById('messageModal');
 const body=document.getElementById('messageModalBody');
 const title=document.getElementById('messageModalTitle');
 const date=document.getElementById('messageModalDate');
 const name=document.getElementById('messageModalName');
 const email=document.getElementById('messageModalEmail');
 const reply=document.getElementById('messageReply');
 function openMessage(row){
   title.textContent='Message dari '+row.dataset.name; date.textContent=row.dataset.date; name.textContent=row.dataset.name; email.textContent=row.dataset.email;
   body.textContent=row.dataset.message; reply.href='mailto:'+encodeURIComponent(row.dataset.email);
   modal.hidden=false; modal.setAttribute('aria-hidden','false'); document.body.classList.add('message-modal-open');
   if(row.classList.contains('is-unread')){ row.classList.remove('is-unread'); row.classList.add('is-read'); const s=row.querySelector('.message-status'); if(s){s.className='message-status read';s.textContent='Read';} fetch('messages.php?read='+encodeURIComponent(row.dataset.messageId),{credentials:'same-origin'}).catch(function(){}); }
   modal.querySelector('.message-modal-close').focus();
 }
 function closeMessage(){ modal.hidden=true; modal.setAttribute('aria-hidden','true'); document.body.classList.remove('message-modal-open'); }
 document.querySelectorAll('.message-row').forEach(function(row){row.addEventListener('click',function(e){if(e.target.closest('a'))return;openMessage(row)});row.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();openMessage(row)}})});
 modal.addEventListener('click',function(e){if(e.target.hasAttribute('data-close-message'))closeMessage()});
 document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!modal.hidden)closeMessage()});
})();
</script>
<?php require_once __DIR__.'/_footer.php'; ?>
