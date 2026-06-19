<script setup lang="ts">
import { computed, onMounted, onUnmounted } from "vue";
import { useAuth } from "~/composables/useAuth";

definePageMeta({
  layout: false,
  middleware: "guest",
});

useSeoMeta({
  title: "ورود"
})

const { $auth } = useNuxtApp();
const auth = $auth || useAuth();

const mobile = ref("");
const password = ref("");
const remember = ref(true);
const passwordType = ref("password");
const loading = ref(false);
const error = ref("");
/** از پاسخ ۴۲۹؛ برای اعلام به مدیر همراه موبایل */
const clientIp = ref<string | null>(null);
const rateLimitSeconds = ref<number | null>(null);
let rateLimitTimer: ReturnType<typeof setInterval> | null = null;

const clearRateLimitCountdown = () => {
  if (rateLimitTimer) {
    clearInterval(rateLimitTimer);
    rateLimitTimer = null;
  }
  rateLimitSeconds.value = null;
};

const formatCountdown = (totalSeconds: number) => {
  if (totalSeconds < 60) {
    return `${totalSeconds} ثانیه`;
  }
  const m = Math.floor(totalSeconds / 60);
  const s = totalSeconds % 60;
  return s ? `${m} دقیقه و ${s} ثانیه` : `${m} دقیقه`;
};

const startRateLimitCountdown = (seconds: number) => {
  clearRateLimitCountdown();
  if (seconds <= 0) return;
  rateLimitSeconds.value = seconds;
  rateLimitTimer = setInterval(() => {
    if (rateLimitSeconds.value === null) return;
    if (rateLimitSeconds.value <= 1) {
      clearRateLimitCountdown();
    } else {
      rateLimitSeconds.value -= 1;
    }
  }, 1000);
};

onMounted(() => {
  if (import.meta.client) {
    const savedRemember = localStorage.getItem("auth_remember");
    if (savedRemember !== null) {
      remember.value = savedRemember === "1";
    }
  }
});

onUnmounted(() => {
  clearRateLimitCountdown();
});

const rateLimitCountdownText = computed(() => {
  if (rateLimitSeconds.value === null) return undefined;
  return `قابل تلاش مجدد پس از ${formatCountdown(rateLimitSeconds.value)}`;
});

const loginAlertDescription = computed(() => {
  const parts: string[] = [];
  if (rateLimitCountdownText.value) {
    parts.push(rateLimitCountdownText.value);
  }
  if (clientIp.value) {
    if (rateLimitCountdownText.value) {
      parts.push("");
    }
    // parts.push(`موبایل: ${(mobile.value || "").trim() || "—"}`);
    parts.push(`آی‌پی: ${clientIp.value}`);
    // parts.push("این دو مقدار را به مدیر بدهید تا قفل ورود (rate limit) برداشته شود.");
  }
  return parts.length ? parts.join("\n") : undefined;
});

const handleLogin = async () => {
  if (!mobile.value || !password.value) {
    error.value = "لطفاً شماره موبایل و رمز عبور را وارد کنید";
    return;
  }

  loading.value = true;
  error.value = "";
  clientIp.value = null;
  clearRateLimitCountdown();

  try {
    const result = await auth.login({
      mobile: mobile.value,
      password: password.value,
      remember: remember.value,
    });

    if (result.success) {
      clientIp.value = null;
      await navigateTo("/");
    } else {
      error.value = result.message || "خطا در ورود. لطفاً دوباره تلاش کنید.";
      clientIp.value = result.clientIp ?? null;
      if (result.retryIn != null) {
        startRateLimitCountdown(result.retryIn);
      }
    }
  } catch (err: any) {
    console.error("Login error:", err);
    error.value = err.message || "خطا در ورود. لطفاً دوباره تلاش کنید.";
  } finally {
    loading.value = false;
  }
};

// اگر Enter زده شد، لاگین کن
const handleKeyPress = (e: KeyboardEvent) => {
  if (e.key === "Enter") {
    handleLogin();
  }
};

const togglePasswordVisibility = () => {
  passwordType.value = passwordType.value === "password" ? "text" : "password";
};
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 px-4">
    <div class="max-w-md w-full space-y-8">
      <div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
          ورود به سیستم
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
          لطفاً اطلاعات خود را وارد کنید
        </p>
      </div>

      <UCard>
        <form @submit.prevent="handleLogin" class="space-y-6">
          <UAlert v-if="error" color="error" variant="soft" :title="error" :description="loginAlertDescription"
            class="mb-4 whitespace-pre-line" />
          <div class="" style="
              display: flex;
              flex-direction: row;
              flex-wrap: nowrap;
              align-items: center;
            ">
            <div class="flex-1">
              <div class="mb-2">
                <UFormGroup label="شماره موبایل" name="mobile" required>
                  <UInput v-model="mobile" type="tel" placeholder="09123456789" size="lg" :disabled="loading"
                    @keypress="handleKeyPress" />
                </UFormGroup>
              </div>
              <div>
                <UFormGroup label="رمز عبور" name="password" required>
                  <div class="relative">
                    <UInput v-model="password" :type="passwordType" placeholder="رمز عبور خود را وارد کنید" size="lg"
                      :disabled="loading" @keypress="handleKeyPress" />
                    <button type="button" @click="togglePasswordVisibility" :disabled="loading"
                      class="absolute left-2 top-1/2 -translate-y-1/2 px-2 py-1 text-sm font-medium text-blue-600 hover:text-blue-800 disabled:opacity-50 showPass">
                      <i v-if="passwordType === 'password'" class="fa fa-eye"></i>
                      <i v-else class="fa fa-eye-slash"></i>
                    </button>
                  </div>
                </UFormGroup>
              </div>
            </div>
            <div class="flex-1">
              <img src="/logo.png" alt="logo" class="w-100" />
            </div>
          </div>
          <div class="flex items-center">
            <UCheckbox v-model="remember" label="مرا به خاطر بسپار" :disabled="loading" />
          </div>
          <div>
            <UButton type="submit" block size="lg" :loading="loading" :disabled="loading">
              ورود
            </UButton>
          </div>
        </form>
      </UCard>
    </div>
  </div>
</template>
<style>
.showPass {
  color: #000;
}

.dark .showPass {
  color: #fff;
}
</style>