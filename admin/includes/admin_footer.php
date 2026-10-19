</main>
</div>
</div>

<script>
const sidebar=document.getElementById('adminSidebar');
const toggle=document.getElementById('sidebarToggle');

if(toggle){
    toggle.addEventListener('click',()=>sidebar.classList.toggle('show'));
}

document.addEventListener('click',(e)=>{
    if(window.innerWidth<=900 && sidebar && toggle && !sidebar.contains(e.target) && !toggle.contains(e.target)){
        sidebar.classList.remove('show');
    }
});
</script>
</body>
</html>