<script setup lang="ts">
</script>

<template>
  <a href="/">
    <div class="team-avatar-container">
      <img src="/logo.png" class="team-avatar" />
    </div>
  </a>
</template>

<style scoped>
.team-avatar-container {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  max-height: 70px;
}

.team-avatar {
  height: 100%;
}

/* .team-avatar {
  filter: drop-shadow(0px 0px 10px #000);
} */
.dark .team-avatar {
  filter: drop-shadow(0px 0px 10px #fff);
}
</style>
