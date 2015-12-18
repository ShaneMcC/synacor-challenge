import java.util.HashMap;

public class getTeleporterValue {
	final static HashMap<Integer, HashMap<Integer, Integer>> cache = new HashMap<Integer, HashMap<Integer, Integer>>();

	public static boolean hasCache(final int x, final int y) {
		if (cache.containsKey(x)) {
			return cache.get(x).containsKey(y);
		}

		return false;
	}

	public static int getCache(final int x, final int y) {
		if (cache.containsKey(x)) {
			return cache.get(x).get(y);
		}

		return 0;
	}

	public static void setCache(final int x, final int y, final int val) {
		if (!cache.containsKey(x)) {
			cache.put(x, new HashMap<Integer, Integer>());
		}

		cache.get(x).put(y, val);
	}

	public static void clearCache() {
		cache.clear();
	}

	public static int ackermann(final int x, final int y, final int r8) {
		// System.out.println("ackermann(" + x + ", " + y + ");");
		int result = 0;
		if (hasCache(x, y)) { return getCache(x, y); }

		if (x == 0) {
			result = (y + 1) % 32768;
		} else if (y == 0) {
			result = ackermann(x - 1, r8, r8);
		} else {
			result = ackermann(x - 1, ackermann(x, y - 1, r8), r8);
		}

		setCache(x, y, result);
		return result;
	}

	public static void main(final String[] args) {

		for (int r8 = 0; r8 < 32768; r8++) {
			if (r8 % 100 == 0) { System.out.println("Trying: " + r8); }

			clearCache();
			if (ackermann(4, 1, r8) == 6) {
				System.out.println("Found Answer: " + r8);
				System.exit(0);
			}
		}

		System.out.println("No answer found.");
	}

}
