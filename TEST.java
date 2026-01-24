class A{
    protected int x;
    final int y = 10; int z = y;
}
class B{
    void test(){
        System.out.println(new A().x);
    }
    
}
public class TEST {
    public static void main(String[] args) {
        // A a = new A();
        B b = new B();
        // b.test();
        // System.out.println(a.x);
                System.out.println(new A().y);

    }
}
